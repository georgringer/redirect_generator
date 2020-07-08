<?php
declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Command;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;
use GeorgRinger\RedirectGenerator\Repository\RedirectRepository;
use GeorgRinger\RedirectGenerator\Service\CsvReader;
use GeorgRinger\RedirectGenerator\Service\ExportService;
use GeorgRinger\RedirectGenerator\Service\UrlMatcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class ExportRedirectCommand extends Command
{

    /**
     * @inheritDoc
     */
    public function configure()
    {
        $this->setDescription('Export redirects as csv')
            ->addArgument('target', InputArgument::REQUIRED, 'Target')
            ->addOption(
                'transform-target-url',
                'transform',
                InputOption::VALUE_NONE,
                'If this option is set, the target url will be transformed in a readable url'
            )
            ->setHelp('Export all redirects as CSV');
    }

    /**
     * Executes the command for adding a redirect
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $file = (string)$input->getArgument('target');
        if (!StringUtility::endsWith($file, '.csv')) {
            $io->error(sprintf('Target must end with .csv, given "%s"', $file));
            return 0;
        }

        $transformTargetUrl = $input->hasOption('transform-target-url') && $input->getOption('transform-target-url') === true;
        $exportService = GeneralUtility::makeInstance(ExportService::class);
        $data = $exportService->run($transformTargetUrl);
        if (empty($data)) {
            $io->success('No redirects found!');
            return 0;
        }

        $csv = new CsvReader();
        $csv->heading = true;
        $csv->titles = array_keys($data[0]);
        $csv->save($file, $data);

        $io->success(sprintf('CSV generated, handled %s redirects!', count($data)));

        return 0;
    }

}
