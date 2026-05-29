<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;

#[AsController]
readonly class ImportModuleController
{
    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($request);
        $view->makeDocHeaderModuleMenu();
        return $view->renderResponse('Import/Overview');
    }
}
