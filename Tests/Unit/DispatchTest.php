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

use Nimut\TestingFramework\TestCase\UnitTestCase;

/**
 * Test case for class tx_t3deploy_dispatch.
 *
 * @package t3deploy
 * @author Oliver Hader <oliver.hader@aoe.com>
 */
class DispatchTest extends UnitTestCase
{
    const ClassPrefix = 'tx_t3deploy_';
    const ClassSuffix = 'Controller';

    /**
     * @var string
     */
    protected $testClassName;

    /**
     * @var tx_t3deploy_dispatch
     */
    protected $dispatch;

    /**
     * Sets up the test cases.
     *
     * @return void
     */
    public function setUp()
    {
        $_SERVER['argv'] = [];

        $this->testClassName = uniqid('testClassName');
        eval('class ' . self::ClassPrefix . $this->testClassName . self::ClassSuffix . ' {}');

        $this->dispatch = new tx_t3deploy_dispatch();
    }

    /**
     * Cleans up the test cases.
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->dispatch);
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
                $this->testClassName,
                'test'
            ]
        ];

        $testMock = $this->getMockBuilder($this->testClassName)
            ->setMethods(['testAction'])
            ->disableOriginalConstructor()
            ->getMock();
        $testMock->expects($this->once())->method('testAction')->willReturn($this->testClassName);

        $this->dispatch->setCliArguments($cliArguments);
        $this->dispatch->setClassInstance(
            self::ClassPrefix . $this->testClassName . self::ClassSuffix,
            $testMock
        );
        $result = $this->dispatch->dispatch();

        $this->assertEquals($this->testClassName, $result);
    }
}