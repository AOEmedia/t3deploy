<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2016 AOE GmbH <dev@aoe.com>
*  All rights reserved
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * General CLI dispatcher for the t3deploy extension.
 *
 * @package t3deploy
 * @author Oliver Hader <oliver.hader@aoe.com>
 */
class tx_t3deploy_dispatch extends \TYPO3\CMS\Core\Controller\CommandLineController {
	const ExtKey = 't3deploy';
	const Mask_ClassName = 'tx_t3deploy_%sController';
	const Mask_ClassFile = 'Classes/class.tx_t3deploy_%sController.php';
	const Mask_Action = '%sAction';

	/**
	 * @var array
	 */
	protected $classInstances = array();

	/**
	 * Creates this object.
	 */
	public function __construct() {
		parent::__construct();

		$this->setCliOptions();

		$this->cli_help = array_merge($this->cli_help, array(
			'name' => 'tx_t3deploy_dispatch',
			'synopsis' => self::ExtKey . ' controller action ###OPTIONS###',
			'description' => 'TYPO3 dispatcher for database related operations.',
			'examples' => 'typo3/cli_dispatch.phpsh ' . self::ExtKey . ' database updateStructure',
			'author' => '(c) 2012 - 2016 AOE GmbH <dev@aoe.com>',
		));
	}

	/**
	 * Sets the CLI arguments.
	 *
	 * @param array $arguments
	 * @return void
	 */
	public function setCliArguments(array $arguments) {
		$this->cli_args = $arguments;
	}

	/**
	 * Gets or generates an instance of the given class name.
	 *
	 * @param string $className
	 * @return object
	 */
	public function getClassInstance($className) {
		if (!isset($this->classInstances[$className])) {
			$this->classInstances[$className] = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance($className);
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
	public function setClassInstance($className, $classInstance) {
		$this->classInstances[$className] = $classInstance;
	}

	/**
	 * Dispatches the requested actions to the accordant controller.
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function dispatch() {
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

		if (!is_callable(array($instance, $actionName))) {
			throw new Exception('The action ' . $action . ' is not implemented in controller ' . $controller);
		}

		$result = call_user_func_array(
			array($instance, $actionName),
			array($this->cli_args)
		);

		return $result;
	}

	/**
	 * Sets the CLI options for help.
	 *
	 * @return void
	 */
	protected function setCliOptions() {
		$this->cli_options = array(
			array('--verbose', 'Report changes'),
			array('-v', 'Same as --verbose'),
			array('--execute', 'Execute changes (updates, removals)'),
			array('-e', 'Same as --execute'),
			array('--remove', 'Include structure differences for removal'),
			array('-r', 'Same as --remove'),
			array('--drop-keys', 'Removes key modifications that will cause errors'),
			array('--dump-file', 'Dump changes to file'),
			array('--excludes', 'Exclude update types (add,change,create_table,change_table,drop,drop_table,clear_table)')
		);
	}
}
