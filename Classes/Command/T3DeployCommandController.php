<?php

namespace AOE\T3Deploy\Command;

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

use AOE\T3Deploy\Utility\SqlStatementUtility;
use TYPO3\CMS\Core\Category\CategoryRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use TYPO3\CMS\Install\Service\SqlExpectedSchemaService;
use TYPO3\CMS\Install\Service\SqlSchemaMigrationService;

/**
 * Class T3DeployCommandController
 */
class T3DeployCommandController extends CommandController
{
    /**
	 * List of all possible update types:
	 *	+ add, change, drop, create_table, change_table, drop_table, clear_table
	 * List of all sensible update types:
	 *	+ add, change, create_table, change_table
	 */
    const UpdateTypes_List = 'add,change,create_table,change_table';
    const RemoveTypes_list = 'drop,drop_table,clear_table';

    /**
     * @var array
     */
    protected $consideredTypes = [];
    /**
     * @var SqlSchemaMigrationService
     */
    protected $schemaMigrationService;

    /**
     * @var CategoryRegistry
     */
    protected $categoryRegistry;

    /**
     * @var SqlExpectedSchemaService
     */
    protected $expectedSchemaService;

    /**
     * @param SqlSchemaMigrationService $sqlSchemaMigrationService
     */
    public function injectSqlSchemaMigrationService(SqlSchemaMigrationService $schemaMigrationService)
    {
        $this->schemaMigrationService = $schemaMigrationService;
    }

    /**
     * @param CategoryRegistry $categoryRegistry
     */
    public function injectCategoryRegistry(CategoryRegistry $categoryRegistry)
    {
        $this->categoryRegistry = $categoryRegistry;
    }

    /**
     * @param SqlExpectedSchemaService $expectedSchemaService
     */
    public function injectSqlExpectedSchemaService(SqlExpectedSchemaService $expectedSchemaService)
    {
        $this->expectedSchemaService = $expectedSchemaService;
    }

    /**
     * Creates an structure.sql to ensure that the Database Schema matches requirement
     *
     * Examples:
     *
     * $ typo3cms t3deploy:updatestructure
     *
     * @param bool $execute
     * @param bool $remove
     * @param bool $dropKeys
     * @param string $dumpFile
     * @param bool $verbose
     * @param string $excludes
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    public function updateStructureCommand($execute = false, $remove = false, $dropKeys = false, $dumpFile = 'structure.sql', $verbose = false, $excludes = '')
    {
        $arguments = [
            'execute' => $execute,
            'remove' => $remove,
            'dropKeys' => $dropKeys,
            'dumpFile' => $dumpFile,
            'verbose' => $verbose,
            'excludes' => $excludes,
        ];

        $this->setConsideredTypes($this->getUpdateTypes());

        if (!file_exists(dirname($dumpFile))) {
            throw new \InvalidArgumentException(sprintf(
                'directory %s does not exist or is not readable', dirname($dumpFile)
            ));
        }
        if (file_exists($dumpFile) && !is_writable($dumpFile)) {
            throw new \InvalidArgumentException(sprintf(
                'file %s is not writable', $dumpFile
            ));
        }

        $result = $this->executeUpdateStructureUntilNoMoreChanges($arguments, $allowKeyModifications);

        file_put_contents($dumpFile, $result);
        $result = sprintf("Output written to %s\n", $dumpFile);


        if ($execute) {
            $result .= ($result ? PHP_EOL : '') . $this->executeUpdateStructureUntilNoMoreChanges($arguments, $allowKeyModifications);
        }

        return $result;
    }

    /**
     * call executeUpdateStructure until there are no more changes.
     *
     * The install tool sometimes relies on the user hitting the "update" button multiple times. This method
     * encapsulates that behaviour.
     *
     * @see executeUpdateStructure()
     * @param array $arguments
     * @param bool $allowKeyModifications
     * @return string
     */
    private function executeUpdateStructureUntilNoMoreChanges(array $arguments, $allowKeyModifications = false)
    {
        $result = '';
        $iteration = 1;
        $loopResult = '';
        do {
            $previousLoopResult = $loopResult;
            $loopResult = $this->executeUpdateStructure($arguments, $allowKeyModifications);
            if ($loopResult === $previousLoopResult) {
                break;
            }

            $result .= sprintf("\n# Iteration %d\n%s", $iteration++, $loopResult);

            if ($iteration > 10) {
                $result .= "\nGiving up after 10 iterations.";
                break;
            }
        } while (!empty($loopResult));

        return $result;
    }

    /**
     * Executes the database structure updates.
     *
     * @param array $arguments Optional arguments passed to this action
     * @param boolean $allowKeyModifications Whether to allow key modifications
     *
     * @throws
     *
     * @return string
     */
    private function executeUpdateStructure($arguments, $allowKeyModifications = false)
    {
        $result = '';

        $isExecuteEnabled = $arguments['execute'];
        $isRemovalEnabled = $arguments['remove'];
        $isVerboseEnabled = $arguments['verbose'];
        $hasExcludes = $arguments['excludes'];

        $changes = $this->schemaMigrationService->getUpdateSuggestions(
            $this->getStructureDifferencesForUpdate($allowKeyModifications)
        );

        if ($isRemovalEnabled) {
            // Disable the delete prefix, thus tables and fields can be removed directly:
            $this->schemaMigrationService->setDeletedPrefixKey('');

            // Add types considered for removal:
            $this->addConsideredTypes($this->getRemoveTypes());
            // Merge update suggestions:
            $removals = $this->schemaMigrationService->getUpdateSuggestions(
                $this->getStructureDifferencesForRemoval($allowKeyModifications),
                'remove'
            );
            $changes = array_merge($changes, $removals);
        }

        if ($hasExcludes) {
            $excludes = explode(',', $arguments['excludes']);
            $this->removeConsideredTypes($excludes);
        }

        if ($isExecuteEnabled || $isVerboseEnabled) {
            $statements = [];

            // Concatenates all statements:
            foreach ($this->consideredTypes as $consideredType) {
                if (isset($changes[$consideredType]) && is_array($changes[$consideredType])) {
                    $statements += $changes[$consideredType];
                }
            }

            $statements = SqlStatementUtility::sortStatements($statements);

            if ($isExecuteEnabled) {
                foreach ($statements as $statement) {
                    $GLOBALS['TYPO3_DB']->admin_query($statement);
                }
            }

            if ($isVerboseEnabled) {
                $result = implode(PHP_EOL, $statements);
            }

        }

        SqlStatementUtility::checkChangesSyntax($result);

        return $result;
    }

    /**
     * Gets the differences in the database structure by comparing
     * the current structure with the SQL definitions of all extensions
     * and the TYPO3 core in t3lib/stddb/tables.sql.
     *
     * This method searches for fields/tables to be added/updated.
     *
     * @param boolean $allowKeyModifications Whether to allow key modifications
     * @return array The database statements to update the structure
     */
    private function getStructureDifferencesForUpdate($allowKeyModifications = FALSE)
    {

        $differences = $this->schemaMigrationService->getDatabaseExtra(
            $this->getDefinedFieldDefinitions(),
            $this->schemaMigrationService->getFieldDefinitions_database()
        );

        if (!$allowKeyModifications) {
            $differences = $this->removeKeyModifications($differences);
        }

        return $differences;
    }

    /**
     * Gets the defined field definitions from the ext_tables.sql files.
     *
     * @return array The accordant definitions
     */
    private function getDefinedFieldDefinitions()
    {
        $cacheTables = $this->categoryRegistry->getDatabaseTableDefinitions();
        $content = $this->schemaMigrationService->getFieldDefinitions_fileContent(
            implode(chr(10), $this->getAllRawStructureDefinitions()) . $cacheTables
        );
        return $content;
    }

    /**
     * Gets all structure definitions of extensions the TYPO3 Core.
     *
     * @return array All structure definitions
     */
    private function getAllRawStructureDefinitions()
    {

        $packageStates = include(PATH_typo3conf . 'PackageStates.php');

        $tmp = $GLOBALS['TYPO3_LOADED_EXT'];

        $GLOBALS['TYPO3_LOADED_EXT'] = array_merge($packageStates['packages'], $GLOBALS['TYPO3_LOADED_EXT']);

        $expectedSchemaString = $this->expectedSchemaService->getTablesDefinitionString(TRUE);
        $rawDefinitions = $this->schemaMigrationService->getStatementArray($expectedSchemaString, TRUE);

        $GLOBALS['TYPO3_LOADED_EXT'] = $tmp;

        return $rawDefinitions;
    }

    /**
     * Removes key modifications that will cause errors.
     *
     * @param array $differences The differences to be cleaned up
     * @return array The cleaned differences
     */
    protected function removeKeyModifications(array $differences)
    {
        $differences = $this->unsetSubKey($differences, 'extra', 'keys', 'whole_table');
        $differences = $this->unsetSubKey($differences, 'diff', 'keys');

        return $differences;
    }

    /**
     * Unsets a subkey in a given differences array.
     *
     * @param array $differences
     * @param string $type e.g. extra or diff
     * @param string $subKey e.g. keys or fields
     * @param string $exception e.g. whole_table that stops the removal
     * @return array
     */
    private function unsetSubKey(array $differences, $type, $subKey, $exception = '')
    {
        if (isset($differences[$type])) {
            foreach ($differences[$type] as $table => $information) {
                $isException = ($exception && isset($information[$exception]) && $information[$exception]);
                if (isset($information[$subKey]) && $isException === FALSE) {
                    unset($differences[$type][$table][$subKey]);
                }
            }
        }

        return $differences;
    }

    /**
     * Sets the types considered to be executed (updates and/or removal).
     *
     * @param array $consideredTypes
     * @return void
     * @see updateStructureAction()
     */
    public function setConsideredTypes(array $consideredTypes)
    {
        $this->consideredTypes = $consideredTypes;
    }

    /**
     * Adds considered types.
     *
     * @param array $consideredTypes
     * @return void
     * @see updateStructureAction()
     */
    private function addConsideredTypes(array $consideredTypes)
    {
        $this->consideredTypes = array_unique(
            array_merge($this->consideredTypes, $consideredTypes)
        );
    }

    /**
     * Removes defined types from considered types.
     *
     * @param array $removals
     * @return void
     * @see updateStructureAction()
     */
    public function removeConsideredTypes(array $removals)
    {
        $this->consideredTypes = array_diff($this->consideredTypes, $removals);
    }

    /**
     * Gets the defined remove types.
     *
     * @return array
     */
    private function getRemoveTypes()
    {
        return GeneralUtility::trimExplode(',', self::RemoveTypes_list, TRUE);
    }

    /**
     * Gets the differences in the database structure by comparing
     * the current structure with the SQL definitions of all extensions
     * and the TYPO3 core in t3lib/stddb/tables.sql.
     *
     * This method searches for fields/tables to be removed.
     *
     * @param boolean $allowKeyModifications Whether to allow key modifications
     * @return array The database statements to update the structure
     */
    protected function getStructureDifferencesForRemoval($allowKeyModifications = FALSE)
    {
        $differences = $this->schemaMigrationService->getDatabaseExtra(
            $this->schemaMigrationService->getFieldDefinitions_database(),
            $this->getDefinedFieldDefinitions()
        );

        if (!$allowKeyModifications) {
            $differences = $this->removeKeyModifications($differences);
        }

        return $differences;
    }

    /**
     * Gets the defined update types.
     *
     * @return array
     */
    private function getUpdateTypes()
    {
        return GeneralUtility::trimExplode(',', self::UpdateTypes_List, TRUE);
    }

}
