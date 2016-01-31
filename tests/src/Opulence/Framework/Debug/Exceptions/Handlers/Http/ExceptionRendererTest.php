<?php
/**
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (C) 2016 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */
namespace Opulence\Framework\Debug\Exceptions\Handlers\Http;

use Exception;
use LogicException;
use Opulence\Http\HttpException;
use Opulence\Http\Requests\Request;
use Opulence\Http\Responses\JsonResponse;
use Opulence\Http\Responses\Response;
use Opulence\Tests\Framework\Debug\Exceptions\Handlers\Http\Mocks\ExceptionRenderer as MockRenderer;
use Opulence\Views\Compilers\ICompiler;
use Opulence\Views\Factories\IViewFactory;
use Opulence\Views\IView;

/**
 * Tests the HTTP exception renderer
 */
class ExceptionRendererTest extends \PHPUnit_Framework_TestCase
{
    /** @var MockRenderer The renderer to use in tests */
    private $renderer = null;
    /** @var IViewFactory|\PHPUnit_Framework_MockObject_MockObject The view factory to use in tests */
    private $viewFactory = null;
    /** @var ICompiler|\PHPUnit_Framework_MockObject_MockObject The view compiler to use in tests */
    private $viewCompiler = null;

    /**
     * Sets up the tests
     */
    public function setUp()
    {
        $this->viewFactory = $this->getMock(IViewFactory::class);
        $this->viewCompiler = $this->getMock(ICompiler::class);
        $this->renderer = new MockRenderer(true);

        // The tests will output data, which we want to buffer
        ob_start();
    }

    /**
     * Does some housekeeping before ending the tests
     */
    public function tearDown()
    {
        ob_end_clean();
    }

    /**
     * Tests that the response is null before being rendered
     */
    public function testExceptionThrownWhenGettingResponseBeforeItIsRendered()
    {
        $this->setExpectedException(LogicException::class);
        $this->renderer->getResponse();
    }

    /**
     * Tests rendering an HTTP exception with an HTML view
     */
    public function testRenderingHttpExceptionWithView()
    {
        $this->setViewComponents();
        $ex = new HttpException(404, "foo");
        $view = $this->getMock(IView::class);
        $this->viewFactory->expects($this->once())
            ->method("has")
            ->with("errors/html/404")
            ->willReturn(true);
        $this->viewFactory->expects($this->once())
            ->method("create")
            ->with("errors/html/404")
            ->willReturn($view);
        $this->viewCompiler->expects($this->once())
            ->method("compile")
            ->with($view)
            ->willReturn("bar");
        $this->renderer->render($ex);
        $this->assertInstanceOf(Response::class, $this->renderer->getResponse());
        $this->assertEquals("bar", $this->renderer->getResponse()->getContent());
        $this->assertEquals(404, $this->renderer->getResponse()->getStatusCode());
    }

    /**
     * Tests rendering an HTTP exception without setting view compiler and factory
     */
    public function testRenderingHttpExceptionWithoutSettingViewCompilerAndFactory()
    {
        $ex = new HttpException(404, "foo");
        $this->viewFactory->expects($this->never())
            ->method("has");
        $this->renderer->render($ex);
        $this->assertEquals($ex->getMessage(), $this->renderer->getResponse()->getContent());
        $this->assertEquals(404, $this->renderer->getResponse()->getStatusCode());
    }

    /**
     * Tests rendering an HTTP exception without a view in the development environment
     */
    public function testRenderingHttpExceptionWithoutViewInDevelopmentEnvironment()
    {
        $this->setViewComponents();
        $ex = new HttpException(404, "foo");
        $this->viewFactory->expects($this->once())
            ->method("has")
            ->with("errors/html/404")
            ->willReturn(false);
        $this->renderer->render($ex);
        $this->assertEquals($ex->getMessage(), $this->renderer->getResponse()->getContent());
        $this->assertEquals(404, $this->renderer->getResponse()->getStatusCode());
    }

    /**
     * Tests rendering an HTTP exception without a view in the production environment
     */
    public function testRenderingHttpExceptionWithoutViewInProductionEnvironment()
    {
        $this->renderer = new MockRenderer(false);
        $this->setViewComponents();
        $ex = new HttpException(404, "foo");
        $this->viewFactory->expects($this->once())
            ->method("has")
            ->with("errors/html/404")
            ->willReturn(false);
        $this->renderer->render($ex);
        $this->assertEquals("Something went wrong", $this->renderer->getResponse()->getContent());
        $this->assertEquals(404, $this->renderer->getResponse()->getStatusCode());
    }

    /**
     * Tests rendering a JSON view
     */
    public function testRenderingJsonView()
    {
        $this->setViewComponents();
        $this->viewCompiler->expects($this->once())
            ->method("compile")
            ->willReturn(json_encode(["foo" => "bar"]));
        /** @var Request|\PHPUnit_Framework_MockObject_MockObject $request */
        $request = $this->getMock(Request::class, [], [], "", false);
        $request->expects($this->exactly(3))
            ->method("isJson")
            ->willReturn(true);
        $this->renderer->setRequest($request);
        $ex = new Exception();
        $view = $this->getMock(IView::class);
        $this->viewFactory->expects($this->once())
            ->method("has")
            ->with("errors/json/500")
            ->willReturn(true);
        $this->viewFactory->expects($this->once())
            ->method("create")
            ->with("errors/json/500")
            ->willReturn($view);
        $this->viewCompiler->expects($this->once())
            ->method("compile")
            ->with($view)
            ->willReturn("bar");
        $this->renderer->render($ex);
        $this->assertInstanceOf(JsonResponse::class, $this->renderer->getResponse());
        $this->assertEquals(500, $this->renderer->getResponse()->getStatusCode());
        $this->assertEquals(json_encode(["foo" => "bar"]), $this->renderer->getResponse()->getContent());
    }

    /**
     * Tests rendering a non-HTTP exception with a view
     */
    public function testRenderingNonHttpExceptionWithView()
    {
        $this->setViewComponents();
        $ex = new Exception("foo");
        $view = $this->getMock(IView::class);
        $this->viewFactory->expects($this->once())
            ->method("has")
            ->with("errors/html/500")
            ->willReturn(true);
        $this->viewFactory->expects($this->once())
            ->method("create")
            ->with("errors/html/500")
            ->willReturn($view);
        $this->viewCompiler->expects($this->once())
            ->method("compile")
            ->with($view)
            ->willReturn("bar");
        $this->renderer->render($ex);
        $this->assertEquals("bar", $this->renderer->getResponse()->getContent());
        $this->assertEquals(500, $this->renderer->getResponse()->getStatusCode());
    }

    /**
     * Tests rendering a non-HTTP exception without a view in the development environment
     */
    public function testRenderingNonHttpExceptionWithoutViewInDevelopmentEnvironment()
    {
        $this->setViewComponents();
        $ex = new Exception("foo");
        $this->viewFactory->expects($this->once())
            ->method("has")
            ->with("errors/html/500")
            ->willReturn(false);
        $this->renderer->render($ex);
        $this->assertEquals($ex->getMessage(), $this->renderer->getResponse()->getContent());
        $this->assertEquals(500, $this->renderer->getResponse()->getStatusCode());
    }

    /**
     * Tests rendering a non-HTTP exception without a view in the production environment
     */
    public function testRenderingNonHttpExceptionWithoutViewInProductionEnvironment()
    {
        $this->renderer = new MockRenderer(false);
        $this->setViewComponents();
        $ex = new Exception("foo");
        $this->viewFactory->expects($this->once())
            ->method("has")
            ->with("errors/html/500")
            ->willReturn(false);
        $this->renderer->render($ex);
        $this->assertEquals("Something went wrong", $this->renderer->getResponse()->getContent());
        $this->assertEquals(500, $this->renderer->getResponse()->getStatusCode());
    }

    /**
     * Sets view components in the renderer
     */
    private function setViewComponents()
    {
        $this->renderer->setViewFactory($this->viewFactory);
        $this->renderer->setViewCompiler($this->viewCompiler);
    }
}