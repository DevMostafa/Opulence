<?php
/**
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (C) 2015 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */
/**
 * Defines the base application test case
 */
namespace Opulence\Framework\Testing\PhpUnit;

use Opulence\Applications\Application;
use Opulence\Debug\Exceptions\Handlers\IExceptionHandler;
use Opulence\Ioc\IContainer;
use PHPUnit_Framework_TestCase;

abstract class ApplicationTestCase extends PHPUnit_Framework_TestCase
{
    /** @var Application The application */
    protected $application = null;
    /** @var IContainer The IoC container */
    protected $container = null;

    /**
     * Tears down the tests
     */
    public function tearDown()
    {
        $this->application->shutDown();
    }

    /**
     * Gets the kernel exception handler
     *
     * @return IExceptionHandler The exception handler used in the kernel
     */
    abstract protected function getExceptionHandler();

    /**
     * Sets the instance of the application and IoC container to use in tests
     */
    abstract protected function setApplicationAndIocContainer();
}