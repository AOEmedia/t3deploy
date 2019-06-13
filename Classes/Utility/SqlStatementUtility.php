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

namespace AOE\T3Deploy\Utility;

class SqlStatementUtility
{

    /**
     * returns true if the given statement looks as if it drops a (primary) key
     *
     * @param $statement
     * @return bool
     */
    public static function isDropKeyStatement($statement)
    {
        return strpos($statement, ' DROP ') !== false && strpos($statement, ' KEY') !== false;
    }

    /**
     * sorts the statements in an array
     *
     * @param array $statements
     * @return array
     */
    public static function sortStatements($statements)
    {
        $newStatements = [];
        foreach ($statements as $key => $statement) {
            if (SqlStatementUtility::isDropKeyStatement($statement)) {
                $newStatements[$key] = $statement;
            }
        }
        foreach ($statements as $key => $statement) {
            // writing the statement again, does not change its position
            // this saves a condition check
            $newStatements[$key] = $statement;
        }

        return $newStatements;
    }

    /**
     * performs some basic checks on the database changes to identify most common errors
     *
     * @param string $changes the changes to check
     * @throws \Exception if the file seems to contain bad data
     */
    public static function checkChangesSyntax($changes)
    {
        if (strlen($changes) < 10) {
            return;
        }
        $checked = substr(ltrim($changes), 0, 10);
        if ($checked !== strtoupper(trim($checked))) {
            throw new \Exception(
                'Changes for schema_up seem to contain weird data, it starts with this:' .
                PHP_EOL . substr($changes, 0, 200) . PHP_EOL .
                '==================================' .
                PHP_EOL . 'If the file is ok, please add your conditions to file 
                res/extensions/t3deploy/classes/class.tx_t3deploy_databaseController.php in t3deploy.');
        }
    }
}
