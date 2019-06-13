<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 AOE GmbH <dev@aoe.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Controller\CommandLineController;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * General CLI dispatcher for the t3deploy extension.
 *
 * @package t3deploy
 * @author Oliver Hader <oliver.hader@aoe.com>
 */
class tx_t3deploy_dispatch extends CommandLineController
{
    const ExtKey = 't3deploy';
    const Mask_ClassName = 'tx_t3deploy_%sController';
    const Mask_ClassFile = 'Classes/class.tx_t3deploy_%sController.php';
    const Mask_Action = '%sAction';

    /**
     * @var array
     */
    protected $classInstances = [];

    /**
     * Creates this object.
     */
    public function __construct()
    {
        parent::__construct();

        $this->setCliOptions();

        $this->cli_help = array_merge($this->cli_help, [
            'name' => 'tx_t3deploy_dispatch',
            'synopsis' => self::ExtKey . ' controller action ###OPTIONS###',
            'description' => 'TYPO3 dispatcher for database related operations.',
            'examples' => 'typo3/cli_dispatch.phpsh ' . self::ExtKey . ' database updateStructure',
            'author' => '(c) 2012 - 2016 AOE GmbH <dev@aoe.com>',
        ]);
    }

    /**
     * Sets the CLI arguments.
     *
     * @param array $arguments
     * @return void
     */
    public function setCliArguments(array $arguments)
    {
        $this->cli_args = $arguments;
    }

    /**
     * Gets or generates an instance of the given class name.
     *
     * @param string $className
     * @return object
     */
    public function getClassInstance($className)
    {
        if (!isset($this->classInstances[$className])) {
            $this->classInstances[$className] = GeneralUtility::makeInstance($className);
        }
        return $this->classInstances[$className];
    }

    /**
     * Sets an instance for the given class name.
     *
     * @param string $className
     * @param object $classInstance
     * @return void
     */
    public function setClassInstance($className, $classInstance)
    {
        $this->classInstances[$className] = $classInstance;
    }

    /**
     * Dispatches the requested actions to the accordant controller.
     *
     * @return mixed
     * @throws Exception
     */
    public function dispatch()
    {
        $controller = (string)$this->cli_args['_DEFAULT'][1];
        $action = (string)$this->cli_args['_DEFAULT'][2];

        if (!$controller || !$action) {
            $this->cli_validateArgs();
            $this->cli_help();
            exit(1);
        }

        $className = sprintf(self::Mask_ClassName, $controller);
        $classFile = sprintf(self::Mask_ClassFile, $controller);
        $actionName = sprintf(self::Mask_Action, $action);

        if (!class_exists($className)) {
            GeneralUtility::requireOnce(PATH_tx_t3deploy . $classFile);
        }

        $instance = $this->getClassInstance($className);

        if (!is_callable([$instance, $actionName])) {
            throw new Exception('The action ' . $action . ' is not implemented in controller ' . $controller);
        }

        $result = call_user_func_array(
            [$instance, $actionName],
            [$this->cli_args]
        );

        return $result;
    }

    /**
     * Sets the CLI options for help.
     *
     * @return void
     */
    protected function setCliOptions()
    {
        $this->cli_options = [
            ['--verbose', 'Report changes'],
            ['-v', 'Same as --verbose'],
            ['--execute', 'Execute changes (updates, removals)'],
            ['-e', 'Same as --execute'],
            ['--remove', 'Include structure differences for removal'],
            ['-r', 'Same as --remove'],
            ['--drop-keys', 'Removes key modifications that will cause errors'],
            ['--dump-file', 'Dump changes to file'],
            ['--excludes', 'Exclude update types (add,change,create_table,change_table,drop,drop_table,clear_table)']
        ];
    }
}
