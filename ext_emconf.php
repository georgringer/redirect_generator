<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Redirect generator',
    'description' => '',
    'category' => 'frontend',
    'author' => 'Georg Ringer',
    'author_email' => 'mail@ringer.it',
    'state' => 'beta',
    'clearCacheOnLoad' => true,
    'version' => '0.1.0',
    'constraints' =>
        [
            'depends' => [
                'typo3' => '9.5.9-10.9.90',
                'redirects' => '9.5.9-10.9.90'
            ],  
            'conflicts' => [],
            'suggests' => [],
        ]
];
