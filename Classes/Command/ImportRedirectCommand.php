<?php
declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Command;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;
use GeorgRinger\RedirectGenerator\Exception\DuplicateException;
use GeorgRinger\RedirectGenerator\Repository\RedirectRepository;
use GeorgRinger\RedirectGenerator\Service\CsvReader;
use GeorgRinger\RedirectGenerator\Service\UrlMatcher;
use GeorgRinger\RedirectGenerator\Utility\NotificationHandler;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

class ImportRedirectCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var RedirectRepository */
    protected $redirectRepository;

    /** @var UrlMatcher */
    protected $urlMatcher;

    /** @var NotificationHandler */
    protected $notificationHandler;

    /** @var ExtensionConfiguration */
    protected $extensionConfiguration;

    /** @var array */
    protected $externalDomains = [];

    public function __construct(
        string $name = null,
        NotificationHandler $notificationHandler,
        ExtensionConfiguration $extensionConfiguration
    ) {
        $this->redirectRepository = GeneralUtility::makeInstance(RedirectRepository::class);
        $this->urlMatcher = GeneralUtility::makeInstance(UrlMatcher::class);
        $this->notificationHandler = $notificationHandler;
        $this->extensionConfiguration = $extensionConfiguration;

        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    public function configure()
    {
        $this->setDescription('Import redirect')
            ->addArgument('file', InputArgument::REQUIRED, 'File to be imported')
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
            )
            ->setHelp('Import a CSV file as redirects');
    }

    /**
     * Executes the command for importing redirects
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $filePath = $input->getArgument('file');
        $dryRun = ($input->hasOption('dry-run') && $input->getOption('dry-run') != false);
        if ($input->hasOption('external-domains')) {
            $this->setExternalDomains((string)$input->getOption('external-domains'));
        }
        if ($dryRun) {
            $io->warning('Dry run enabled!');
        }

        /** @var CsvReader */
        $csvReader = GeneralUtility::makeInstance(CsvReader::class);
        $csvReader->heading = true;
        $csvReader->delimiter = ';';
        $csvReader->enclosure = '';

        try {
            $this->validateFilePath($filePath);
            $csvReader->parse($filePath);

            $data = $csvReader->data;
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
                $this->logger->debug($msg);
                $this->logger->debug(\print_r($response['ok'], true));
            }
            if (!empty($response['error'])) {
                $errorMessages = [];
                foreach ($response['error'] as $messages) {
                    $errorMessages = \array_merge($errorMessages, $messages);
                }
                $io->error(NotificationHandler::ERROR_MESSAGE . LF . implode(LF, $errorMessages));
                $this->logger->error(NotificationHandler::ERROR_MESSAGE);
                $this->logger->error(\print_r($errorMessages, true));
            }
            if (!empty($response['skipped'])) {
                $msg = \sprintf(NotificationHandler::IMPORT_SKIPPED_MESSAGE, \count($response['skipped']));
                $io->note($msg);
                $this->logger->warning($msg);
                $this->logger->warning(\print_r($response['skipped'], true));
            }
            if (!empty($response['duplicates'])) {
                $msg = \sprintf(NotificationHandler::IMPORT_DUPLICATES_MESSAGE, \count($response['duplicates']));
                $io->note($msg);
                $this->logger->warning($msg);
                $this->logger->warning(\print_r($response['duplicates'], true));
            }

            $this->notificationHandler->sendImportResultAsEmail($response);
        } catch (\UnexpectedValueException $exception) {
            $this->notificationHandler->sendThrowableAsEmail($exception);
            $this->logger->error($exception->getMessage(), $this->notificationHandler->throwableToArray($exception));
            $io->error($exception->getMessage());
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
                if ($item['external'] ?? '' === '1' || $this->isExternalDomain($item['target'] ?? '')) {
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
            } catch (DuplicateException $e) {
                $response['duplicates'][] = $e->getMessage();
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
            if (strpos($target, $externalDomain) === 0) {
                return true;
            }
        }
        return false;
    }

    protected function getConfigurationFromItem(array $item): Configuration
    {
        /** @var Configuration */
        $configuration = GeneralUtility::makeInstance(Configuration::class);
        if (isset($item['status_code'])) {
            $configuration->setTargetStatusCode((int)$item['status_code']);
        }

        return $configuration;
    }

    /**
     * @param string $filePath
     * @return bool
     */
    protected function validateFilePath(string $filePath): bool
    {
        if (!is_file($filePath)) {
            throw new \UnexpectedValueException(sprintf('File "%s" does not exist', $filePath), 1568544111);
        }
        if (!\str_ends_with(strtolower($filePath), '.csv')) {
            throw new \UnexpectedValueException(sprintf('File "%s" is no CSV file', $filePath), 1568544112);
        }

        return true;
    }

    protected function validateCsvHeaders(array $item, int $position): bool
    {
        $requiredFields = ['source', 'target'];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $item)) {
                throw new \UnexpectedValueException(sprintf('Key "%s" does not exist in CSV in line %s', $field, $position), 156854413);
            }
            if (empty($item[$field])) {
                throw new \UnexpectedValueException(sprintf('Key "%s" is empty in CSV in line %s', $field, $position), 156854414);
            }
        }

        return true;
    }

    protected function setExternalDomains(string $domains)
    {
        $list = GeneralUtility::trimExplode(',', $domains, true);
        foreach ($list as $item) {
            $this->externalDomains[] = 'http://' . $item;
            $this->externalDomains[] = 'https://' . $item;
        }
    }
}
