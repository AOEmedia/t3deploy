<?php
namespace Aoe\t3deploy\Tests\Unit;

/***************************************************************
*  Copyright notice
*
*  (c) 2018 AOE GmbH <dev@aoe.com>
*  All rights reserved
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

use Nimut\TestingFramework\TestCase\UnitTestCase;

/**
 * Test case for class tx_t3deploy_dispatch.
 *
 * @package t3deploy
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
     * @var \tx_t3deploy_dispatch
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

        $this->dispatch = new \tx_t3deploy_dispatch();
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

        $testMock = $this->getMock($this->testClassName, ['testAction']);
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