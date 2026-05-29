<?php

use GeorgRinger\RedirectGenerator\Controller\ExportModuleController;
use GeorgRinger\RedirectGenerator\Controller\ImportExportModuleController;
use GeorgRinger\RedirectGenerator\Controller\ImportModuleController;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 14) {
    return [
        'redirect_generator' => [
            'parent' => 'site',
            'position' => ['after' => 'site_redirects'],
            'access' => 'user',
            'path' => '/module/site/redirect-generator',
            'iconIdentifier' => 'actions-upload',
            'labels' => 'LLL:EXT:redirect_generator/Resources/Private/Language/Modules/importexport.xlf',
            'routes' => [
                '_default' => [
                    'target' => ImportExportModuleController::class . '::handleImportRequest',
                ],
                'export' => [
                    'target' => ImportExportModuleController::class . '::handleExportRequest',
                ],
            ],
        ],
    ];
}

return [
    'redirect_generator_import' => [
        'parent' => 'link_management',
        'access' => 'user',
        'path' => '/module/link-management/redirect-import',
        'iconIdentifier' => 'actions-upload',
        'labels' => 'redirect_generator.modules.import',
        'routes' => [
            '_default' => [
                'target' => ImportModuleController::class . '::handleRequest',
            ],
        ],
    ],
    'redirect_generator_export' => [
        'parent' => 'link_management',
        'access' => 'user',
        'path' => '/module/link-management/redirect-export',
        'iconIdentifier' => 'actions-download',
        'labels' => 'redirect_generator.modules.export',
        'routes' => [
            '_default' => [
                'target' => ExportModuleController::class . '::handleRequest',
            ],
        ],
    ],
];
