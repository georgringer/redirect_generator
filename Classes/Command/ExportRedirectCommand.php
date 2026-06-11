<?php
declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Command;

use GeorgRinger\RedirectGenerator\Service\ExportService;
use GeorgRinger\RedirectGenerator\Utility\NotificationHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('redirect:export', 'Export redirects as CSV')]
class ExportRedirectCommand extends Command
{
    public function __construct(
        private readonly ExportService $exportService,
        private readonly NotificationHandler $notificationHandler,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addArgument('target', InputArgument::REQUIRED, 'Target')
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
        $data = $this->exportService->run($transformTargetUrl);
        if (empty($data)) {
            $data['ok'] = 'No redirects found!';
            $this->notificationHandler->sendExportResultAsEmail($data);
            $io->success($data['ok']);
            return 0;
        }

        $handle = fopen($file, 'w');
        fputcsv($handle, array_keys($data[0]), ',', '"', '');
        foreach ($data as $row) {
            fputcsv($handle, array_values($row), ',', '"', '');
        }
        fclose($handle);

        $data['ok'] = \sprintf('CSV generated, handled %s redirects!', count($data));
        $this->notificationHandler->sendExportResultAsEmail($data);
        $io->success($data['ok']);

        return 0;
    }

}
