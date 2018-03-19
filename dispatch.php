<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2018 AOE GmbH <dev@aoe.com>
*  All rights reserved
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

echo \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
    \Aoe\T3deploy\Controller\Dispatcher::class
)->dispatch() . PHP_EOL;
