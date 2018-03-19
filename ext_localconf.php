<?php
defined('TYPO3_MODE') or die();

// Register t3deploy as a possible key for CLI calls
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['cliKeys']['t3deploy'] = [
    function () {
        $t3deployCliController = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\Aoe\T3deploy\Controller\T3deployCliController::class);
        echo $t3deployCliController->dispatch() . PHP_EOL;
    },
    '_CLI_t3deploy'
];
