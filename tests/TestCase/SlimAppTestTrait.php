<?php

namespace App\Test\TestCase;

use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Odan\Slim\Session\Adapter\MemorySessionAdapter;
use Odan\Slim\Session\Session;
use Psr\Container\ContainerInterface as Container;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Slim\App;

/**
 * Trait.
 */
trait SlimAppTestTrait
{
    /**
     * @var App|null
     */
    protected $app;

    protected function bootSlim()
    {
        $this->app = require __DIR__ . '/../../config/bootstrap.php';
    }

    protected function shutdownSlim()
    {
        $this->app = null;
    }

    /**
     * Get container.
     *
     * @throws \ReflectionException
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        if ($this->app === null) {
            throw new RuntimeException('App must be initialized');
        }

        $container = $this->app->getContainer();

        $this->setContainer($container, Session::class, call_user_func(function () {
            $session = new Session(new MemorySessionAdapter());
            $session->setOptions([
                'cache_expire' => 60,
                'name' => 'app',
                'use_cookies' => false,
                'cookie_httponly' => false,
            ]);

            return $session;
        }));

        $this->setContainer($container, LoggerInterface::class, call_user_func(function () {
            $logger = new Logger('test');

            return $logger->pushHandler(new NullHandler());
        }));

        return $container;
    }

    /**
     * Set container entry.
     *
     * @param Container $container
     * @param string $key
     * @param mixed $value
     *
     * @throws \ReflectionException
     *
     * @return void
     */
    protected function setContainer(Container $container, string $key, $value): void
    {
        $class = new ReflectionClass(\Pimple\Container::class);

        $property = $class->getProperty('frozen');
        $property->setAccessible(true);

        $values = $property->getValue($container);
        unset($values[$key]);
        $property->setValue($container, $values);

        // The same like '$container[$key] = $value;' but compatible with phpstan
        $method = new ReflectionMethod(\Pimple\Container::class, 'offsetSet');
        $method->invoke($container, $key, $value);
    }
}