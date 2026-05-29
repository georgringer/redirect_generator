<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Redirect generator',
    'description' => 'Import + Export redirects',
    'category' => 'frontend',
    'author' => 'Georg Ringer',
    'author_email' => 'mail@ringer.it',
    'state' => 'beta',
    'clearCacheOnLoad' => true,
    'version' => '3.0.0',
    'constraints' =>
        [
            'depends' => [
                'typo3' => '13.4.20-14.3.99',
                'redirects' => '13.4.20-14.3.99',
            ],
            'conflicts' => [],
            'suggests' => [],
        ]
];
