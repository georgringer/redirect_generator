<?php
declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Command;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;
use GeorgRinger\RedirectGenerator\Exception\ConflictingDuplicateException;
use GeorgRinger\RedirectGenerator\Exception\NonConflictingDuplicateException;
use GeorgRinger\RedirectGenerator\Repository\RedirectRepository;
use GeorgRinger\RedirectGenerator\Service\UrlMatcher;
use GeorgRinger\RedirectGenerator\Utility\NotificationHandler;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\CsvUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

#[AsCommand('redirect:import', 'Import redirects from a CSV file')]
class ImportRedirectCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected array $externalDomains = [];

    public function __construct(
        private readonly RedirectRepository $redirectRepository,
        private readonly UrlMatcher $urlMatcher,
        private readonly NotificationHandler $notificationHandler,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'File to be imported')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'If this option is set, the redirects won\'t be added but just the result shown'
            )->addOption(
                'external-domains',
                null,
                InputOption::VALUE_OPTIONAL,
                'List of target domains which are treated as external'
            )->addOption(
                'delete-file',
                null,
                InputOption::VALUE_NONE,
                'Delete the import file after import'
            )->addOption(
                'delimiter',
                null,
                InputOption::VALUE_OPTIONAL,
                'CSV delimiter character: ";" (default), "," or "tab"',
                ';'
            )
            ->setHelp('Import a CSV file as redirects');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $filePath = $input->getArgument('file');
        $dryRun = ($input->hasOption('dry-run') && $input->getOption('dry-run') != false);
        $delimiter = $this->resolveDelimiter((string)$input->getOption('delimiter'));
        if ($input->hasOption('external-domains')) {
            $this->setExternalDomains((string)$input->getOption('external-domains'));
        }
        if ($dryRun) {
            $io->warning('Dry run enabled!');
        }

        try {
            $this->validateFilePath($filePath);
            $rows = CsvUtility::csvToArray((string)file_get_contents($filePath), $delimiter);
            $headers = array_shift($rows);
            $data = array_map(static fn(array $row) => array_combine($headers, $row), $rows);
            if (empty($data)) {
                $allowEmptyFile = $this->extensionConfiguration->get('redirect_generator', 'allow_empty_import_file');
                if ($allowEmptyFile) {
                    $io->success('Skipped empty CSV file.');
                    return 0;
                }
                throw new \UnexpectedValueException('CSV is empty, nothing can be imported!');
            }

            $this->validateCsvHeaders($data[0], 0);

            $response = $this->importItems($data, $dryRun);

            if (!empty($response['ok'])) {
                $msg = \sprintf(NotificationHandler::IMPORT_SUCCESS_MESSAGE, \count($response['ok']));
                $io->success($msg);
                $this->logger->debug($msg. PHP_EOL . \implode(PHP_EOL, $response['ok']));
            }
            if (!empty($response['error'])) {
                $errorMessages = [];
                foreach ($response['error'] as $messages) {
                    $errorMessages = \array_merge($errorMessages, $messages);
                }
                $msg = NotificationHandler::ERROR_MESSAGE . PHP_EOL . \implode(PHP_EOL, $errorMessages);
                $io->error($msg);
                $this->logger->error($msg);
            }
            if (!empty($response['skipped'])) {
                $msg = \sprintf(NotificationHandler::IMPORT_SKIPPED_MESSAGE, \count($response['skipped']));
                $io->note($msg);
                $this->logger->warning($msg . PHP_EOL . \implode(PHP_EOL, $response['skipped']));
            }
            if (!empty($response['duplicates']['conflicting'])) {
                $msg = \sprintf(
                    NotificationHandler::IMPORT_DUPLICATES_CONFLICTING_MESSAGE,
                    \count($response['duplicates']['conflicting'])
                );
                $io->note($msg);
                $this->logger->warning($msg . PHP_EOL . \implode(PHP_EOL, $response['duplicates']['conflicting']));
            }
            if (!empty($response['duplicates']['non_conflicting'])) {
                $msg = \sprintf(
                    NotificationHandler::IMPORT_DUPLICATES_NON_CONFLICTING_MESSAGE,
                    \count($response['duplicates']['non_conflicting'])
                );
                $io->info($msg);
                $this->logger->info($msg . PHP_EOL . \implode(PHP_EOL, $response['duplicates']['non_conflicting']));
            }

            $this->notificationHandler->sendImportResultAsEmail($response);
        } catch (\UnexpectedValueException $exception) {
            $this->notificationHandler->sendThrowableAsEmail($exception);
            $this->logger->error($exception->getMessage(), $this->notificationHandler->throwableToArray($exception));
            $io->error($exception->getMessage());
            return 2;
        } finally {
            if ($input->hasOption('delete-file') && $input->getOption('delete-file') != false) {
                \unlink($filePath);
            }
        }

        if (!empty($response['error'])) {
            return 2;
        }

        if (!empty($response['skipped']) || !empty($response['duplicates']['conflicting'])) {
            return 1;
        }

        return 0;
    }

    protected function importItems(array $items, bool $dryRun): array
    {
        $response = [
            'ok' => [],
            'skipped' => [],
            'duplicates' => [],
            'error' => []
        ];
        foreach ($items as $position => $item) {
            try {
                $this->validateCsvHeaders($item, $position);
                if ($this->targetEqualsSource($item['target'], $item['source'])) {
                    $response['skipped'][] = \sprintf('Skipping redirect "%s": It has itself as target!', $item['source']);
                    continue;
                }
                if ($item['target'] === 'x') {
                    continue;
                }

                $configuration = $this->getConfigurationFromItem($item);
                if (($item['external'] ?? '') === '1' || $this->isExternalDomain($item['target'] ?? '')) {
                    $targetUrl = $item['target'];
                } else {
                    $result = $this->urlMatcher->getUrlData($item['target']);
                    $targetUrl = $result->getLinkString();

                    $routeArguments = $result->getPageArguments()->getRouteArguments();
                    if (!empty($routeArguments)) {
                        $targetUrl .= HttpUtility::buildQueryString($routeArguments, '&');
                    }
                }
                $this->redirectRepository->addRedirect($item['source'], $targetUrl, $configuration, $dryRun);

                $response['ok'][] = 'Redirect added: ' . $item['source'] . ' => ' . $item['target'];
            } catch (NonConflictingDuplicateException $e) {
                $response['duplicates']['non_conflicting'][] = $e->getMessage();
            } catch (ConflictingDuplicateException $e) {
                $response['duplicates']['conflicting'][] = $e->getMessage();
            } catch (\Exception $e) {
                $response['error'][$e->getCode()][] = $e->getMessage();
            }
        }

        return $response;
    }

    protected function targetEqualsSource(string $target, string $source): bool
    {
        $search = ['http://', 'https://'];
        $target = str_replace($search, '', $target);
        $source = str_replace($search, '', $source);
        return rtrim($target, '/') === rtrim($source, '/');
    }

    protected function isExternalDomain(string $target): bool
    {
        if (empty($this->externalDomains)) {
            return false;
        }
        foreach ($this->externalDomains as $externalDomain) {
            if (str_starts_with($target, $externalDomain)) {
                return true;
            }
        }
        return false;
    }

    protected function getConfigurationFromItem(array $item): Configuration
    {
        $statusCode = isset($item['status_code']) && Configuration::statusCodeIsAllowed((int)$item['status_code'])
            ? (int)$item['status_code']
            : 307;
        return new Configuration(targetStatusCode: $statusCode);
    }

    protected function validateFilePath(string $filePath): void
    {
        if (!is_file($filePath)) {
            throw new \UnexpectedValueException(sprintf('File "%s" does not exist', $filePath), 1568544111);
        }
        if (!\str_ends_with(strtolower($filePath), '.csv')) {
            throw new \UnexpectedValueException(sprintf('File "%s" is no CSV file', $filePath), 1568544112);
        }
    }

    protected function validateCsvHeaders(array $item, int $position): void
    {
        foreach (['source', 'target'] as $field) {
            if (!array_key_exists($field, $item)) {
                throw new \UnexpectedValueException(sprintf('Key "%s" does not exist in CSV in line %s', $field, $position), 156854413);
            }
            if (empty($item[$field])) {
                throw new \UnexpectedValueException(sprintf('Key "%s" is empty in CSV in line %s', $field, $position), 156854414);
            }
        }
    }

    protected function resolveDelimiter(string $value): string
    {
        return match ($value) {
            'tab'   => "\t",
            ','     => ',',
            default => ';',
        };
    }

    protected function setExternalDomains(string $domains): void
    {
        foreach (GeneralUtility::trimExplode(',', $domains, true) as $domain) {
            $this->externalDomains[] = 'http://' . $domain;
            $this->externalDomains[] = 'https://' . $domain;
        }
    }
}
