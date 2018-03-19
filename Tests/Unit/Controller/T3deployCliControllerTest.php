<?php
namespace Aoe\T3deploy\Tests\Unit;

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

use Aoe\T3deploy\Controller\T3deployCliController;
use Aoe\T3deploy\Controller\DatabaseController;
use Nimut\TestingFramework\TestCase\UnitTestCase;

/**
 * Test case for class tx_t3deploy_dispatch.
 *
 * @package t3deploy
 */
class T3DeployCliControllerTest extends UnitTestCase
{
    /**
     * @var T3deployCliController
     */
    protected $t3deployCliController;

    /**
     * Sets up the test cases.
     *
     * @return void
     */
    public function setUp()
    {
        $_SERVER['argv'] = [];
        $this->t3deployCliController = new T3deployCliController();
    }

    /**
     * Cleans up the test cases.
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->t3deployCliController);
        parent::tearDown();
    }

    /**
     * Tests whether a controller is correctly dispatched
     *
     * @test
     */
    public function isControllerActionCorrectlyDispatched()
    {
        $cliArguments = [
            '_DEFAULT' => [
                't3deploy',
                'database',
                'updateStructure'
            ]
        ];

        $testMock = $this->getMock(DatabaseController::class, ['updateStructureAction'], [], '', false);
        $testMock->expects($this->once())->method('updateStructureAction')->willReturn('method called');

        $this->t3deployCliController->setCliArguments($cliArguments);
        $this->t3deployCliController->setClassInstance(DatabaseController::class, $testMock);
        $this->assertEquals('method called', $this->t3deployCliController->dispatch());
    }
}