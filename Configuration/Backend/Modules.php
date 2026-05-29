<?php

use GeorgRinger\RedirectGenerator\Controller\ExportModuleController;
use GeorgRinger\RedirectGenerator\Controller\ImportModuleController;

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
