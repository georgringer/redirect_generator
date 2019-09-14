<?php
declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Command;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;
use GeorgRinger\RedirectGenerator\Domain\Model\Dto\UrlInfo;
use GeorgRinger\RedirectGenerator\Repository\RedirectRepository;
use GeorgRinger\RedirectGenerator\Service\UrlMatcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ImportRedirectCommand extends Command
{

    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure()
    {
        $this->setDescription('Redirect command');
    }

    /**
     * Executes the command for showing sys_log entries
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());


        $from = '/blabla';
        $incomingUrl = 'http://t3-master.vm/en/examples/patches/translate-to-english-copy-mode';

        $redirectRepository = GeneralUtility::makeInstance(RedirectRepository::class);

        $matcher = GeneralUtility::makeInstance(UrlMatcher::class);

        $result = $matcher->getUrlData($incomingUrl);
        $configuration = new Configuration();
        $redirectRepository->addRedirect($from, $result, $configuration);
    }
}
