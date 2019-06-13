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

use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Service\SqlExpectedSchemaService;

/**
 * Test case for class tx_t3deploy_databaseController.
 *
 * @package t3deploy
 * @author Oliver Hader <oliver.hader@aoe.com>
 */
class DatabaseControllerTest extends FunctionalTestCase
{
    /**
     * @var array
     */
    protected $coreExtensionsToLoad = ['core', 'extensionmanager'];

    /**
     * @var array
     */
    protected $testExtensionsToLoad = ['typo3conf/ext/t3deploy', '../../Tests/Fixtures/testextension'];

    /**
     * @var tx_t3deploy_databaseController
     */
    private $controller;

    /**
     * Sets up the test cases.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $expectedSchemaServiceMock = $this->getMockBuilder(SqlExpectedSchemaService::class)
            ->setMethods(['getTablesDefinitionString'])
            ->disableOriginalConstructor()
            ->getMock();

        $expectedSchemaServiceMock->expects($this->any())->method('getTablesDefinitionString')->with(true)->willReturn(
            file_get_contents(PATH_tx_t3deploy . 'Tests/Fixtures/testextension/ext_tables_fixture.sql')
        );

        $this->controller = new tx_t3deploy_databaseController();
        $this->inject($this->controller, 'expectedSchemaService', $expectedSchemaServiceMock);
    }

    /**
     * Cleans up the test cases.
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->testExtensionsName);
        unset($this->testLoadedExtensions);
        unset($this->controller);

        GeneralUtility::flushDirectory($this->getInstancePath());
    }

    /**
     * Tests whether the updateStructure action just reports the changes
     *
     * @test
     * @return void
     */
    public function doesUpdateStructureActionReportChanges()
    {
        $arguments = ['--verbose' => ''];
        $result = $this->controller->updateStructureAction($arguments);

        // Assert that nothing has been created, this is just for reporting:
        $tables = $GLOBALS['TYPO3_DB']->admin_get_tables();
        $pagesFields = $GLOBALS['TYPO3_DB']->admin_get_fields('pages');
        $this->assertFalse(isset($tables['tx_testextension_test']));
        $this->assertNotEquals('varchar(255)', strtolower($pagesFields['alias']['Type']));

        // Assert that changes are reported:
        $this->assertContains('ALTER TABLE pages ADD tx_testextension_field_test', $result);
        $this->assertContains('ALTER TABLE pages CHANGE alias alias varchar(255)', $result);
        $this->assertContains('CREATE TABLE tx_testextension_test', $result);
        $this->assertNotContains('DROP TABLE tx_testextension', $result);
    }

    /**
     * Test whether the updateStructure action just executes the changes.
     *
     * @test
     * @return void
     */
    public function doesUpdateStructureActionExecuteChanges()
    {
        $arguments = ['--execute' => ''];
        $result = $this->controller->updateStructureAction($arguments);

        // Assert that tables have been created:
        $tables = $GLOBALS['TYPO3_DB']->admin_get_tables();
        $pagesFields = $GLOBALS['TYPO3_DB']->admin_get_fields('pages');
        $this->assertTrue(isset($tables['tx_testextension']));
        $this->assertTrue(isset($tables['tx_testextension_test']));
        $this->assertTrue(isset($pagesFields['tx_testextension_field_test']));
        $this->assertEquals('varchar(255)', strtolower($pagesFields['alias']['Type']));

        // Assert that nothing is reported we just want to execute:
        $this->assertEmpty($result);
    }

    /**
     * Test whether the updateStructure action just reports remove old database definitions.
     *
     * @test
     * @return void
     */
    public function doesUpdateStructureActionReportRemovals()
    {
        $arguments = [
            '--remove' => '',
            '--verbose' => ''
        ];

        $result = $this->controller->updateStructureAction($arguments);

        // Assert that nothing has been removed, this is just for reporting:
        $tables = $GLOBALS['TYPO3_DB']->admin_get_tables();
        $this->assertTrue(isset($tables['tx_testextension']));
        $pagesFields = $GLOBALS['TYPO3_DB']->admin_get_fields('pages');
        $this->assertTrue(isset($pagesFields['tx_testextension_field']));

        // Assert that removals are reported:
        $this->assertContains('DROP TABLE tx_testextension', $result);
        $this->assertContains('ALTER TABLE pages DROP tx_testextension_field', $result);
    }

    /**
     * Test whether the updateStructure action remove old database definitions.
     *
     * @test
     * @return void
     */
    public function doesUpdateStructureActionExecuteRemovals()
    {
        $arguments = [
            '--remove' => '',
            '--execute' => ''
        ];

        $result = $this->controller->updateStructureAction($arguments);

        // Assert that tables and columns have been removed:
        $tables = $GLOBALS['TYPO3_DB']->admin_get_tables();
        $this->assertFalse(isset($tables['tx_testextension']));
        $pagesFields = $GLOBALS['TYPO3_DB']->admin_get_fields('pages');
        $this->assertFalse(isset($pagesFields['tx_testextension_field']));

        // Assert that nothing is reported we just want to execute:
        $this->assertEmpty($result);
    }

    /**
     * Test whether the updateStructure action remove old database definitions.
     *
     * test
     * @return void
     */
    public function doesUpdateStructureActionReportDropKeys()
    {
        $arguments = [
            '--drop-keys' => '',
            '--verbose' => ''
        ];

        $result = $this->controller->updateStructureAction($arguments);

        // Assert that nothing has been removed, this is just for reporting:
        $tables = $GLOBALS['TYPO3_DB']->admin_get_tables();
        $this->assertTrue(isset($tables['tx_testextension']));
        $pagesFields = $GLOBALS['TYPO3_DB']->admin_get_fields('pages');
        $this->assertTrue(isset($pagesFields['tx_testextension_field']));

        // Assert that removals are reported:
        $this->assertContains('DROP TABLE tx_testextension', $result);
        $this->assertContains('ALTER TABLE pages DROP tx_testextension_field', $result);
    }

    /**
     * Test whether the updateStructure action dump changes to file.
     *
     * @test
     * @return void
     */
    public function doesUpdateStructureActionDumpChangesToFile()
    {
        $testDumpFile = PATH_tx_t3deploy . 'Tests/test_dumpfile.sql';
        if (file_exists($testDumpFile)) {
            unlink($testDumpFile);
        }
        $this->assertFileNotExists($testDumpFile);

        $arguments = [
            '--verbose' => '',
            '--dump-file' => [$testDumpFile]
        ];

        $result = $this->controller->updateStructureAction($arguments);

        $this->assertFileExists($testDumpFile);
        $testDumpFileContent = file_get_contents($testDumpFile);
        // Assert that changes are dumped:
        $this->assertContains('ALTER TABLE pages ADD tx_testextension_field_test', $testDumpFileContent);
        $this->assertContains('ALTER TABLE pages CHANGE alias alias varchar(255)', $testDumpFileContent);
        $this->assertContains('CREATE TABLE tx_testextension_test', $testDumpFileContent);

        // Assert that dump result is reported:
        $this->assertNotEmpty($result);
    }
}