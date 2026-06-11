<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Controller;

use GeorgRinger\RedirectGenerator\Repository\RedirectRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Localization\LanguageService;

#[AsController]
readonly class ExportModuleController
{
    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private RedirectRepository $redirectRepository,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($request);
        $view->makeDocHeaderModuleMenu();
        $view->assignMultiple([
            'availableRedirectTypes' => $this->redirectRepository->getDistinctColumnValues('redirect_type'),
            'availableCreationTypes' => $this->buildCreationTypeOptions(),
        ]);
        return $view->renderResponse('Export/Overview');
    }

    private function buildCreationTypeOptions(): array
    {
        $usedValues = $this->redirectRepository->getDistinctColumnValues('creation_type');
        $tcaItems = $GLOBALS['TCA']['sys_redirect']['columns']['creation_type']['config']['items'] ?? [];
        $labelMap = [];
        foreach ($tcaItems as $item) {
            $labelMap[$item['value']] = $this->getLanguageService()->sL($item['label']);
        }
        $options = [];
        foreach ($usedValues as $value) {
            $options[] = [
                'value' => (string)$value,
                'label' => $labelMap[$value] ?? (string)$value,
            ];
        }
        return $options;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
