<?php
declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Command;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;
use GeorgRinger\RedirectGenerator\Exception\DuplicateException;
use GeorgRinger\RedirectGenerator\Repository\RedirectRepository;
use GeorgRinger\RedirectGenerator\Service\CsvReader;
use GeorgRinger\RedirectGenerator\Service\UrlMatcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class ImportRedirectCommand extends Command
{

    /** @var RedirectRepository */
    protected $redirectRepository;

    /** @var UrlMatcher */
    protected $urlMatcher;

    /** @var array */
    protected $externalDomains = [];

    public function __construct(string $name = null)
    {
        $this->redirectRepository = GeneralUtility::makeInstance(RedirectRepository::class);
        $this->urlMatcher = GeneralUtility::makeInstance(UrlMatcher::class);

        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    public function configure()
    {
        $this->setDescription('Redirect command')
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

        $csvReader = GeneralUtility::makeInstance(CsvReader::class);
        $csvReader->heading = true;
        $csvReader->delimiter = ',';
        $csvReader->enclosure = '"';
        $csvReader->parse($filePath);

        try {
            $this->validateFilePath($filePath);

            $data = $csvReader->data;
            if (empty($data)) {
                throw new \UnexpectedValueException('CSV is empty, nothing can be imported!');
            }

            $this->validateCsvHeaders($data[0], 0);

            $response = $this->importItems($data, $dryRun);

            if ($response['ok'] > 0) {
                $io->success(sprintf('%s redirects have been added!', $response['ok']));
            }
            if (!empty($response['error'])) {
                $errorMessages = [];
                foreach ($response['error'] as $errorCode => $messages) {
                    $errorMessages[] = implode(LF, $messages);
                }
                $io->error('The following errors happened: ' . LF . implode(LF . LF, $errorMessages));
            }
            if ($response['skipped'] > 0) {
                $io->note(sprintf('%s redirects skipped because source is same as target', $response['skipped']));
            }
            if ($response['duplicates'] > 0) {
                $io->note(sprintf('%s redirects skipped because of duplicates', $response['duplicates']));
            }
        } catch (\UnexpectedValueException $exception) {
            $io->error($exception->getMessage());
        }
    }

    protected function importItems(array $items, bool $dryRun): array
    {
        $response = [
            'ok' => 0,
            'skipped' => 0,
            'duplicates' => 0,
            'error' => []
        ];
        foreach ($items as $position => $item) {
            try {
                $this->validateCsvHeaders($item, $position);
                if ($this->targetEqualsSource($item['target'], $item['source'])) {
                    $response['skipped']++;
                    continue;
                }
                if ($item['target'] === 'x') {
                    continue;
                }

                $configuration = $this->getConfigurationFromItem($item);
                if ($this->isExternalDomain($item['target'])) {
                    $targetUrl = $item['target'];
                } else {
                    $result = $this->urlMatcher->getUrlData($item['target']);
                    $targetUrl = $result->getLinkString();
                }
                $this->redirectRepository->addRedirect($item['source'], $targetUrl, $configuration, $dryRun);

                $response['ok']++;
            } catch (DuplicateException $e) {
                $response['duplicates']++;
            } catch (\Exception $e) {
                $response['error'][$e->getCode()][$e->getMessage()] = $e->getMessage();
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

    protected function getConfigurationFromItem(array $item)
    {
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
        if (!StringUtility::endsWith(strtolower($filePath), '.csv')) {
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
