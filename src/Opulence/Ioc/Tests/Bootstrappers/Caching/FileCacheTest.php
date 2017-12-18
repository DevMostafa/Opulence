<?php

/*
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (C) 2017 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */

namespace Opulence\Ioc\Tests\Bootstrappers\Caching;

use Opulence\Ioc\Bootstrappers\BootstrapperRegistry;
use Opulence\Ioc\Bootstrappers\Caching\FileCache;
use Opulence\Ioc\Bootstrappers\LazyBootstrapper;
use Opulence\Ioc\Tests\Bootstrappers\Mocks\EagerBootstrapper;
use Opulence\Ioc\Tests\Bootstrappers\Mocks\LazyBootstrapper as MockBootstrapper;
use Opulence\Ioc\Tests\Bootstrappers\Mocks\LazyBootstrapperWithTargetedBinding;

/**
 * Tests the bootstrapper file cache
 */
class FileCacheTest extends \PHPUnit\Framework\TestCase
{
    /** @var FileCache The cache to use in tests */
    private $cache = null;
    /** @var BootstrapperRegistry The registry to use in tests */
    private $registry = null;
    /** @var string The path to the cached registry file */
    private $cachedRegistryFilePath = '';

    /**
     * Gets the bindings to lazy bootstrapper class mappings
     *
     * @param string|array $lazyBootstrapperClasses The lazy bootstrapper to create
     * @return array The bindings to lazy bootstrappers
     */
    private static function getBindingsToLazyBootstrappers($lazyBootstrapperClasses)
    {
        $lazyBootstrapperClasses = (array)$lazyBootstrapperClasses;
        $bindingsToLazyBootstrappers = [];

        foreach ($lazyBootstrapperClasses as $lazyBootstrapperClass) {
            /** @var LazyBootstrapper $lazyBootstrapper */
            $lazyBootstrapper = new $lazyBootstrapperClass();

            foreach ($lazyBootstrapper->getBindings() as $boundClass) {
                $targetClass = null;

                if (is_array($boundClass)) {
                    $targetClass = array_values($boundClass)[0];
                    $boundClass = array_keys($boundClass)[0];
                }

                $bindingsToLazyBootstrappers[$boundClass] = [
                    'bootstrapper' => $lazyBootstrapperClass,
                    'target' => $targetClass
                ];
            }
        }

        return $bindingsToLazyBootstrappers;
    }

    /**
     * Sets up the tests
     */
    public function setUp() : void
    {
        $this->cachedRegistryFilePath = __DIR__ . '/files/cachedRegistry.json';
        $this->cache = new FileCache($this->cachedRegistryFilePath);
        $this->registry = new BootstrapperRegistry();
    }

    /**
     * Tears down the tests
     */
    public function tearDown() : void
    {
        if (file_exists($this->cachedRegistryFilePath)) {
            @unlink($this->cachedRegistryFilePath);
        }
    }

    /**
     * Tests flushing the cache
     */
    public function testFlushing() : void
    {
        file_put_contents($this->cachedRegistryFilePath, 'foo');
        $this->cache->flush();
        $this->assertFileNotExists($this->cachedRegistryFilePath);
    }

    /**
     * Tests reading from an expired cache flushes it
     */
    public function testReadingFromExpiredCacheFlushesIt() : void
    {
        // Set the expiration so that it will definitely be more recent than the cached file's last modified time
        $cache = new FileCache($this->cachedRegistryFilePath, time() + 3600);
        $this->writeRegistry([
            'eager' => [EagerBootstrapper::class],
            'lazy' => self::getBindingsToLazyBootstrappers(MockBootstrapper::class)
        ]);
        $this->assertNull($cache->get());
        $this->assertFileNotExists($this->cachedRegistryFilePath);
    }

    /**
     * Tests reading when there is a cached registry
     */
    public function testReadingWhenCachedRegistryExists() : void
    {
        $this->writeRegistry([
            'eager' => [EagerBootstrapper::class],
            'lazy' => self::getBindingsToLazyBootstrappers(MockBootstrapper::class)
        ]);
        $registry = $this->cache->get();
        $this->assertInstanceOf(BootstrapperRegistry::class, $registry);
        $this->assertEquals([EagerBootstrapper::class], $registry->getEagerBootstrappers());
        $this->assertEquals(
            self::getBindingsToLazyBootstrappers(MockBootstrapper::class),
            $registry->getLazyBootstrapperBindings()
        );
    }

    /**
     * Tests reading when there is no cached registry
     */
    public function testReadingWhenNoCachedRegistryExists() : void
    {
        $this->assertNull($this->cache->get());
    }

    /**
     * Tests writing a registry and then reading from it
     */
    public function testWritingAndThenReadingRegistry() : void
    {
        $lazyBootstrapper = new MockBootstrapper();
        $setRegistry = new BootstrapperRegistry();
        $setRegistry->registerEagerBootstrapper(EagerBootstrapper::class);
        $setRegistry->registerLazyBootstrapper($lazyBootstrapper->getBindings(), MockBootstrapper::class);
        $this->cache->set($setRegistry);
        $this->assertEquals($setRegistry, $this->cache->get());
    }

    /**
     * Tests writing a registry with targeted binding and then reading from it
     */
    public function testWritingAndThenReadingRegistryWithTargetedBinding() : void
    {
        $lazyBootstrapper = new LazyBootstrapperWithTargetedBinding();
        $setRegistry = new BootstrapperRegistry();
        $setRegistry->registerEagerBootstrapper(EagerBootstrapper::class);
        $setRegistry->registerLazyBootstrapper($lazyBootstrapper->getBindings(), MockBootstrapper::class);
        $this->cache->set($setRegistry);
        $this->assertEquals($setRegistry, $this->cache->get());
    }

    /**
     * Tests writing a registry
     */
    public function testWritingRegistry() : void
    {
        $lazyBootstrapper = new MockBootstrapper();
        $registry = new BootstrapperRegistry();
        $registry->registerEagerBootstrapper(EagerBootstrapper::class);
        $registry->registerLazyBootstrapper($lazyBootstrapper->getBindings(), MockBootstrapper::class);
        $this->cache->set($registry);
        $this->assertEquals(
            [
                'eager' => [EagerBootstrapper::class],
                'lazy' => self::getBindingsToLazyBootstrappers(MockBootstrapper::class)
            ],
            $this->readFromCachedRegistryFile()
        );
    }

    /**
     * Tests writing a registry with no eager bootstrappers
     */
    public function testWritingRegistryWithNoEagerBootstrappers() : void
    {
        $lazyBootstrapper = new MockBootstrapper();
        $registry = new BootstrapperRegistry();
        $registry->registerLazyBootstrapper($lazyBootstrapper->getBindings(), MockBootstrapper::class);
        $this->cache->set($registry);
        $this->assertEquals(
            [
                'eager' => [],
                'lazy' => self::getBindingsToLazyBootstrappers(MockBootstrapper::class)
            ],
            $this->readFromCachedRegistryFile()
        );
    }

    /**
     * Tests writing a registry with no lazy bootstrappers
     */
    public function testWritingRegistryWithNoLazyBootstrappers() : void
    {
        $registry = new BootstrapperRegistry();
        $registry->registerEagerBootstrapper(EagerBootstrapper::class);
        $this->cache->set($registry);
        $this->assertEquals(
            [
                'eager' => [EagerBootstrapper::class],
                'lazy' => []
            ],
            $this->readFromCachedRegistryFile()
        );
    }

    /**
     * Reads data from the cached registry file
     *
     * @return array The decoded data
     */
    private function readFromCachedRegistryFile()
    {
        return json_decode(file_get_contents($this->cachedRegistryFilePath), true);
    }

    /**
     * Writes data to the registry
     *
     * @param array $data The data to write
     */
    private function writeRegistry(array $data) : void
    {
        file_put_contents($this->cachedRegistryFilePath, json_encode($data));
    }
}
