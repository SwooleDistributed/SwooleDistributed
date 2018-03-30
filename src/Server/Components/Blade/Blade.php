<?php

namespace Server\Components\Blade;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerInterface;
use Illuminate\Contracts\View\Factory;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\ViewServiceProvider;

class Blade
{

    /**
     * Container instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * Engine Resolver
     *
     * @var EngineResolver
     */
    protected $engineResolver;

    protected $cachePath;

    /**
     * Constructor.
     *
     * @param string $cachePath
     * @param Container|ContainerInterface $container
     */
    public function __construct($cachePath, ContainerInterface $container = null)
    {
        $this->cachePath = $cachePath;
        $this->container = $container ?: new Container;
        $this->setupContainer();

        (new ViewServiceProvider($this->container))->register();

        $this->engineResolver = $this->container->make('view.engine.resolver');
    }

    /**
     * Bind required instances for the service provider.
     */
    protected function setupContainer()
    {
        $this->container->bindIf('files', function () {
            return new Filesystem;
        }, true);

        $this->container->bindIf('events', function () {
            return new Dispatcher;
        }, true);

        $this->container->bindIf('config', function () {
            return [
                'view.compiled' => $this->cachePath,
                'view.paths' => [],
            ];
        }, true);
    }

    /**
     * @param $namespace
     * @param $path
     */
    public function addNamespace($namespace, $path)
    {
        $this->viewFactory()->addNamespace($namespace, $path);
    }

    /**
     * Render shortcut.
     *
     * @param  string $view
     * @param  array $data
     * @param  array $mergeData
     *
     * @return string
     */
    public function render($view, $data = [], $mergeData = [])
    {
        return $this->container['view']->make($view, $data, $mergeData)->render();
    }

    /**
     * Get the compiler
     *
     * @return BladeCompiler
     */
    public function compiler()
    {
        $bladeEngine = $this->engineResolver->resolve('blade');

        return $bladeEngine->getCompiler();
    }

    /**
     * @return Factory
     */
    public function viewFactory()
    {
        return $this->container['view'];
    }

	 /**
     * Register a valid view extension and its engine.
     *
     * @param  string    $extension
     * @param  string    $engine
     * @param  \Closure  $resolver
     * @return void
     */
    public function addExtension($extension, $engine, $resolver = null) {
        return $this->container['view']->addExtension($extension, $engine, $resolver = null);
    }
}
