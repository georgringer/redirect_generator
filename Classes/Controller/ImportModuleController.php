<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Site\SiteFinder;

#[AsController]
readonly class ImportModuleController
{
    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private SiteFinder $siteFinder,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($request);
        $view->makeDocHeaderModuleMenu();
        $view->assign('firstSiteDomain', $this->resolveFirstSiteDomain());
        return $view->renderResponse('Import/Overview');
    }

    private function resolveFirstSiteDomain(): string
    {
        $sites = $this->siteFinder->getAllSites();
        $site = reset($sites);
        if ($site === false) {
            return 'https://example.com';
        }
        $base = $site->getBase();
        return $base->getScheme() . '://' . $base->getHost();
    }
}
