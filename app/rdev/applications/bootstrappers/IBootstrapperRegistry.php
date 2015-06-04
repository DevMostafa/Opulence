<?php
/**
 * Copyright (C) 2015 David Young
 *
 * Defines the interface for bootstrapper registries to implement
 */
namespace RDev\Applications\Bootstrappers;
use RuntimeException;

interface IBootstrapperRegistry
{
    /**
     * Gets the mapping of bound classes to their bootstrapper classes
     *
     * @return array The mapping of bound classes to their bootstrapper classes
     */
    public function getBindingsToLazyBootstrapperClasses();

    /**
     * Gets the list of eager bootstrapper classes
     *
     * @return array The list of eager bootstrapper classes
     */
    public function getEagerBootstrapperClasses();

    /**
     * Gets an instance of the bootstrapper class
     *
     * @param string $bootstrapperClass The name of the class whose instance we want
     * @return Bootstrapper The instance of the bootstrapper
     * @throws RuntimeException Thrown if the bootstrapper is not an instance of Bootstrapper
     */
    public function getInstance($bootstrapperClass);

    /**
     * Registers bootstrapper classes in the case that no cached registry was found
     * In this case, all the bootstrappers in this list are instantiated and later written to a cached registry
     *
     * @param array $bootstrapperClasses The list of bootstrapper classes
     */
    public function registerBootstrapperClasses(array $bootstrapperClasses);

    /**
     * Registers eager bootstrappers
     *
     * @param string|array $eagerBootstrapperClasses The eager bootstrapper classes
     */
    public function registerEagerBootstrapper($eagerBootstrapperClasses);

    /**
     * Registers bound classes and their bootstrappers
     *
     * @param string|array $bindings The bindings registered by the bootstrapper
     * @param string $lazyBootstrapperClass The bootstrapper class
     */
    public function registerLazyBootstrapper($bindings, $lazyBootstrapperClass);

    /**
     * Sets the eager and lazy bootstrappers
     */
    public function setBootstrapperDetails();
}