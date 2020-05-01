<?php
declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Command;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;
use GeorgRinger\RedirectGenerator\Repository\RedirectRepository;
use GeorgRinger\RedirectGenerator\Service\UrlMatcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AddRedirectCommand extends Command
{

    /**
     * @inheritDoc
     */
    public function configure()
    {
        $this->setDescription('Add redirect to the redirects table')
            ->addArgument('source', InputArgument::REQUIRED, 'Source')
            ->addArgument('target', InputArgument::REQUIRED, 'Target')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'If this option is set, the redirect won\'t be added'
            )
            ->addOption(
                'status-code',
                'status',
                InputOption::VALUE_OPTIONAL,
                'Define the status code, can be 301,302,303 or 307',
                307
            )
            ->setHelp('Add a single redirect from the given source url to the target url. Target URL must be a valid page!');
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
        $dryRun = $input->hasOption('dry-run') && $input->getOption('dry-run') != false ? true : false;

        if ($dryRun) {
            $io->warning('Dry run enabled!');
        }


        $source = $input->getArgument('source');
        $target = $input->getArgument('target');

        $matcher = GeneralUtility::makeInstance(UrlMatcher::class);
        $configuration = $this->getConfigurationFromInput($input);

        try {
            $result = $matcher->getUrlData($target);

            if ($dryRun) {
                $io->success('The following redirect would have been added:');
            } else {
                $redirectRepository = GeneralUtility::makeInstance(RedirectRepository::class);
                $redirectRepository->addRedirect($source, $result->getLinkString(), $configuration, $dryRun);
                $io->success('Redirect has been added!');
            }

            $language = $result->getSiteRouteResult()->getLanguage();
            $io->table([], [
                ['Status Code', $configuration->getTargetStatusCode()],
                ['Source', $source],
                ['Target', $target],
                ['Target Page', $result->getPageArguments()->getPageId()],
                ['Target Language', sprintf('%s (ID %s)', $language->getTypo3Language(), $language->getLanguageId())],
                ['Target Link', $result->getLinkString()],

            ]);
        } catch (\Exception $e) {
            $io->error(sprintf('Following error occured: %s (%s)', LF . $e->getMessage(), $e->getCode()));
        }

        return 0;
    }

    protected function getConfigurationFromInput(InputInterface $input): Configuration
    {
        $configuration = new Configuration();


        if ($input->hasOption('status-code') && !$configuration->statusCodeIsAllowed((int)$input->getOption('status-code'))) {
            throw new \RuntimeException('Status code wrong');
        }
        $configuration->setTargetStatusCode((int)$input->getOption('status-code'));

        return $configuration;
    }
}
