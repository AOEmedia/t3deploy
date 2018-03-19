<?php
namespace Aoe\T3deploy\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 AOE GmbH <dev@aoe.com>
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

use TYPO3\CMS\Core\Category\CategoryRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Install\Service\SqlExpectedSchemaService;
use TYPO3\CMS\Install\Service\SqlSchemaMigrationService;

/**
 * Controller that handles database actions of the t3deploy process inside TYPO3.
 *
 * @package t3deploy
 */
class DatabaseController
{
    /*
     * List of all possible update types:
     *  + add, change, drop, create_table, change_table, drop_table, clear_table
     * List of all sensible update types:
     *  + add, change, create_table, change_table
     */
    const UpdateTypes_List = 'add,change,create_table,change_table';
    const RemoveTypes_list = 'drop,drop_table,clear_table';

    /**
     * @var \TYPO3\CMS\Install\Service\SqlSchemaMigrationService
     */
    protected $schemaMigrationService;

    /**
     * @var \TYPO3\CMS\Install\Service\SqlExpectedSchemaService
     */
    protected $expectedSchemaService;

    /**
     * @var \TYPO3\CMS\Core\Category\CategoryRegistry
     */
    protected $categoryRegistry;

    /**
     * @var array
     */
    protected $consideredTypes;

    /**
     * Creates this object.
     */
    public function __construct()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        $this->schemaMigrationService = $objectManager->get(SqlSchemaMigrationService::class);
        $this->expectedSchemaService = $objectManager->get(SqlExpectedSchemaService::class);
        $this->categoryRegistry = $objectManager->get(CategoryRegistry::class);

        $this->setConsideredTypes($this->getUpdateTypes());
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
    public function addConsideredTypes(array $consideredTypes)
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
     * Updates the database structure.
     *
     * @param array $arguments Optional arguments passed to this action
     * @return string
     * @throws \Exception
     */
    public function updateStructureAction(array $arguments)
    {
        $isExecuteEnabled = (isset($arguments['--execute']) || isset($arguments['-e']));
        $isRemovalEnabled = (isset($arguments['--remove']) || isset($arguments['-r']));
        $isModifyKeysEnabled = isset($arguments['--drop-keys']);

        $result = $this->executeUpdateStructureUntilNoMoreChanges($arguments, $isModifyKeysEnabled);

        if(isset($arguments['--dump-file'])) {
            $dumpFileName = $arguments['--dump-file'][0];

            if (!file_exists(dirname($dumpFileName))) {
                throw new \InvalidArgumentException(sprintf(
                    'directory %s does not exist or is not readable', dirname($dumpFileName)
                ));
            }

            if (file_exists($dumpFileName) && !is_writable($dumpFileName)) {
                throw new \InvalidArgumentException(sprintf(
                    'file %s is not writable', $dumpFileName
                ));
            }

            file_put_contents($dumpFileName, $result);
            $result = sprintf("Output written to %s\n", $dumpFileName);
        }

        if ($isExecuteEnabled) {
            $result .= ($result ? PHP_EOL : '')
                            . $this->executeUpdateStructureUntilNoMoreChanges($arguments, $isRemovalEnabled);
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
     * @throws \Exception
     */
    protected function executeUpdateStructureUntilNoMoreChanges(array $arguments, $allowKeyModifications = false)
    {
        $result = '';
        $iteration = 1;
        $loopResult = '';

        do {
            $previousLoopResult = $loopResult;
            $loopResult = $this->executeUpdateStructure($arguments, $allowKeyModifications);

            if ($loopResult == $previousLoopResult) {
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
     * @return string
     * @throws \Exception
     */
    protected function executeUpdateStructure(array $arguments, $allowKeyModifications = false)
    {
        $result = '';

        $isExecuteEnabled = (isset($arguments['--execute']) || isset($arguments['-e']));
        $isRemovalEnabled = (isset($arguments['--remove']) || isset($arguments['-r']));
        $isVerboseEnabled = (isset($arguments['--verbose']) || isset($arguments['-v']));
        $hasExcludes      = (isset($arguments['--excludes']) && is_array($arguments['--excludes']));

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
            $excludes = explode(',', $arguments['--excludes'][0]);
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

            $statements = $this->sortStatements($statements);

            if ($isExecuteEnabled) {
                foreach ($statements as $statement) {
                    $GLOBALS['TYPO3_DB']->admin_query($statement);
                }
            }

            if ($isVerboseEnabled) {
                $result = implode(PHP_EOL, $statements);
            }
        }

        $this->checkChangesSyntax($result);

        return $result;
    }

    /**
     * performs some basic checks on the database changes to identify most common errors
     *
     * @param string $changes the changes to check
     * @throws \Exception if the file seems to contain bad data
     */
    protected function checkChangesSyntax($changes)
    {
        if (strlen($changes) < 10) return;

        $checked = substr(ltrim($changes), 0, 10);

        if ($checked != trim(strtoupper($checked))) {
            throw new \Exception(
                'Changes for schema_up seem to contain weird data, it starts with this:' . PHP_EOL
                    . substr($changes, 0, 200).PHP_EOL.'==================================' . PHP_EOL
                    . 'If the file is ok, please add your conditions to file '
                    . 'res/extensions/t3deploy/classes/class.tx_t3deploy_databaseController.php in t3deploy.'
            );
        }
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
    protected function unsetSubKey(array $differences, $type, $subKey, $exception = '')
    {
        if (isset($differences[$type])) {
            foreach ($differences[$type] as $table => $information) {
                $isException = ($exception && isset($information[$exception]) && $information[$exception]);
                if (isset($information[$subKey]) && $isException === false) {
                    unset($differences[$type][$table][$subKey]);
                }
            }
        }

        return $differences;
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
    protected function getStructureDifferencesForUpdate($allowKeyModifications = false)
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
     * Gets the differences in the database structure by comparing
     * the current structure with the SQL definitions of all extensions
     * and the TYPO3 core in t3lib/stddb/tables.sql.
     *
     * This method searches for fields/tables to be removed.
     *
     * @param boolean $allowKeyModifications Whether to allow key modifications
     * @return array The database statements to update the structure
     */
    protected function getStructureDifferencesForRemoval($allowKeyModifications = false)
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
     * Gets the defined field definitions from the ext_tables.sql files.
     *
     * @return array The accordant definitions
     */
    protected function getDefinedFieldDefinitions()
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
    protected function getAllRawStructureDefinitions()
    {

        $expectedSchemaString = $this->expectedSchemaService->getTablesDefinitionString(true);
        $rawDefinitions = $this->schemaMigrationService->getStatementArray($expectedSchemaString, true);

        return $rawDefinitions;
    }

    /**
     * Gets the defined update types.
     *
     * @return array
     */
    protected function getUpdateTypes()
    {
        return \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', self::UpdateTypes_List, true);
    }

    /**
     * Gets the defined remove types.
     *
     * @return array
     */
    protected function getRemoveTypes()
    {
        return \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', self::RemoveTypes_list, true);
    }

    /**
     * sorts the statements in an array
     *
     * @param array $statements
     * @return array
     */
    protected function sortStatements($statements)
    {
        $newStatements = [];

        foreach($statements as $key=>$statement) {
            if($this->isDropKeyStatement($statement)) {
                $newStatements[$key] = $statement;
            }
        }

        foreach($statements as $key=>$statement) {
            // writing the statement again, does not change its position
            // this saves a condition check
            $newStatements[$key] = $statement;
        }

        return $newStatements;
    }

    /**
     * returns true if the given statement looks as if it drops a (primary) key
     *
     * @param $statement
     * @return bool
     */
    protected function isDropKeyStatement($statement)
    {
        return strpos($statement, ' DROP ') !== false && strpos($statement, ' KEY') !== false;
    }
}
