<?php
declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Command;

use GeorgRinger\RedirectGenerator\Service\CsvReader;
use GeorgRinger\RedirectGenerator\Service\ExportService;
use GeorgRinger\RedirectGenerator\Utility\NotificationHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ExportRedirectCommand extends Command
{

    /** @var NotificationHandler */
    protected $notificationHandler;

    public function __construct(
        string $name = '',
        ?NotificationHandler $notificationHandler = null
    )
    {
        $this->notificationHandler = $notificationHandler;

        parent::__construct('redirect:export');
    }

    public function configure(): void
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $file = (string)$input->getArgument('target');
        if (!\str_ends_with($file, '.csv')) {
            $data['error'] = sprintf('Target must end with .csv, given "%s"', $file);
            $this->notificationHandler->sendExportResultAsEmail($data);
            $io->error($data['error']);
            return 0;
        }

        $transformTargetUrl = $input->hasOption('transform-target-url') && $input->getOption('transform-target-url') === true;
        $exportService = GeneralUtility::makeInstance(ExportService::class);
        $data = $exportService->run($transformTargetUrl);
        if (empty($data)) {
            $data['ok'] = 'No redirects found!';
            $this->notificationHandler->sendExportResultAsEmail($data);
            $io->success($data['ok']);
            return 0;
        }

        $csv = new CsvReader();
        $csv->heading = true;
        $csv->titles = array_keys($data[0]);
        $csv->save($file, $data);

        $data['ok'] = \sprintf('CSV generated, handled %s redirects!', count($data));
        $this->notificationHandler->sendExportResultAsEmail($data);
        $io->success($data['ok']);

        return 0;
    }

}
