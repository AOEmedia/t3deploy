<?php

$EM_CONF[$_EXTKEY] = array(
    'title' => 't3deploy TYPO3 dispatcher',
    'description' => 'TYPO3 dispatcher for database related operations',
    'category' => 'be',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'author' => 'AOE GmbH',
    'author_email' => 'dev@aoe.com',
    'version' => '1.2.0',
    'constraints' => array(
        'depends' => array(
            'typo3'=>'7.6.0-8.7.99',
        ),
        'conflicts' => array(),
        'suggests' => array(),
    )
);
