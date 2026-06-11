<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Controller;

use GeorgRinger\RedirectGenerator\Repository\RedirectRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsController]
readonly class ImportExportModuleController
{
    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private RedirectRepository $redirectRepository,
        private UriBuilder $uriBuilder,
        private IconFactory $iconFactory,
        private SiteFinder $siteFinder,
    ) {}

    public function handleImportRequest(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($request);
        $this->addNavigationButtons($view, 'import');
        $view->assign('firstSiteDomain', $this->resolveFirstSiteDomain());
        return $view->renderResponse('ImportExport/Import');
    }

    public function handleExportRequest(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($request);
        $this->addNavigationButtons($view, 'export');
        $isV14Plus = GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() >= 14;
        $view->assignMultiple([
            'availableRedirectTypes' => $isV14Plus ? $this->redirectRepository->getDistinctColumnValues('redirect_type') : [],
            'availableCreationTypes' => $this->buildCreationTypeOptions(),
        ]);
        return $view->renderResponse('ImportExport/Export');
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

    private function addNavigationButtons(ModuleTemplate $view, string $active): void
    {
        $buttonBar = $view->getDocHeaderComponent()->getButtonBar();
        $lang = $this->getLanguageService();

        $importButton = $buttonBar->makeLinkButton()
            ->setHref((string)$this->uriBuilder->buildUriFromRoute('redirect_generator'))
            ->setTitle($lang->sL('LLL:EXT:redirect_generator/Resources/Private/Language/Modules/import.xlf:title'))
            ->setIcon($this->iconFactory->getIcon('actions-upload', IconSize::SMALL))
            ->setShowLabelText(true)
            ->setClasses($active === 'import' ? 'active' : '');

        $exportButton = $buttonBar->makeLinkButton()
            ->setHref((string)$this->uriBuilder->buildUriFromRoute('redirect_generator.export'))
            ->setTitle($lang->sL('LLL:EXT:redirect_generator/Resources/Private/Language/Modules/export.xlf:title'))
            ->setIcon($this->iconFactory->getIcon('actions-download', IconSize::SMALL))
            ->setShowLabelText(true)
            ->setClasses($active === 'export' ? 'active' : '');

        $buttonBar->addButton($importButton, ButtonBar::BUTTON_POSITION_LEFT, 1);
        $buttonBar->addButton($exportButton, ButtonBar::BUTTON_POSITION_LEFT, 2);
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
