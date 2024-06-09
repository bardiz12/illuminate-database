<?php

namespace Illuminate\Database\Capsule;

use Illuminate\Support\Fluent;
use Psr\Container\ContainerInterface;
use Illuminate\Contracts\Config\Repository;

trait CapsuleManagerTrait
{
    /**
     * The current globally used instance.
     *
     * @var object
     */
    protected static $instance;

    /**
     * The container instance.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Setup the IoC container instance.
     *
     * @param  ContainerInterface  $container
     * @return void
     */
    protected function setupContainer(ContainerInterface $container)
    {
        $this->container = $container;

        if (!$this->container->has('config')) {
            throw new \Exception("ConfigRepository not available in container ");
        }

        $this->config = $this->container->get(Repository::class);
    }

    /**
     * Make this capsule instance available globally.
     *
     * @return void
     */
    public function setAsGlobal()
    {
        static::$instance = $this;
    }

    /**
     * Get the IoC container instance.
     *
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set the IoC container instance.
     *
     * @param  ContainerInterface  $container
     * @return void
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }
}
