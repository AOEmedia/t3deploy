# t3deploy

TYPO3 dispatcher for database related operations.


## Build information
[![Build Status](https://travis-ci.org/AOEpeople/t3deploy.svg?branch=master)](https://travis-ci.org/AOEpeople/t3deploy)
[![Code Coverage](https://scrutinizer-ci.com/g/AOEpeople/t3deploy/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/AOEpeople/t3deploy/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/AOEpeople/t3deploy/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/AOEpeople/t3deploy/?branch=master)

## Usage

**Add new database definitions**
```
php typo3/cli_dispatch.phpsh t3deploy database updateStructure --verbose --execute
```
**Remove old database definitions**
```
php typo3/cli_dispatch.phpsh t3deploy database updateStructure --remove --verbose --execute
```
**Only report new database definitions and removals**
```
php typo3/cli_dispatch.phpsh t3deploy database updateStructure --remove --verbose
```
**Only report new database definitions and removals to file**
```
php typo3/cli_dispatch.phpsh t3deploy database updateStructure --remove --verbose --dump-file update_dump.sql
```
**Exclude the types drop_table and clear_table from update database definitions**
```
php typo3/cli_dispatch.phpsh t3deploy database updateStructure --remove --verbose --excludes=drop_table,clear_table
```

**Options**
* --verbose (-v): Report changes
* --execute (-e): Execute changes (updates, removals)
* --remove (-r): Include structure differences for removal
* --drop-keys: Removes key modifications that will cause errors
* --dump-file: Dump changes to file
* --excludes: Exclude update types (add,change,create_table,change_table,drop,drop_table,clear_table)

## Requirements

TYPO3 7.6+

## Authors

Oliver Hader, Daniel Poetzinger, Michael Klapper

See also the list of [contributors](https://github.com/AOEpeople/t3deploy/contributors) who participated in this project.

## Copyright / License

Copyright: (c) 2012 - 2018, AOE GmbH
License: GPLv3, <http://www.gnu.org/licenses/gpl-3.0.en.html>