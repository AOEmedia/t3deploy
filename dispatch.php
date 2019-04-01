<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2018 AOE GmbH <dev@aoe.com>
*  All rights reserved
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

if (!defined ('TYPO3_cliMode')) {
    die('Access denied: CLI only.');
}

require_once PATH_tx_t3deploy . 'Classes/class.tx_t3deploy_dispatch.php';
echo \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_t3deploy_dispatch')->dispatch() . PHP_EOL;
