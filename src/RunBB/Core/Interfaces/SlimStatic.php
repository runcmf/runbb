<?php
namespace RunBB\Core\Interfaces;

use Slim\App;
use RunBB\Core\Statical\Manager;

class SlimStatic
{
    /**
    * Boots up SlimStatic by registering its proxies with Statical.
    *
    * @param \Slim\App $slim
    * @return \RunBB\Core\Statical\Manager
    */
    public static function boot(App $slim)
    {
        // set Slim application for syntactic-sugar proxies
        SlimSugar::$slim = $slim;

        // create a new Manager
        $manager = new Manager();

        // Add proxies that use the Slim instance
        $aliases = ['Config', 'Route', 'Router', 'ForumEnv', 'ForumSettings', 'User', 'Lang'];
        static::addInstances($aliases, $manager, $slim);

        // Add special-case Slim container instance
        $aliases = ['Container'];
        static::addInstances($aliases, $manager, $slim->getContainer());

        // Add services that are resolved out of the Slim container
        static::addServices($manager, $slim);

        return $manager;
    }

    /**
    * Adds instances to the Statical Manager
    *
    * @param string[] $aliases
    * @param \RunBB\Core\Statical\Manager $manager
    * @param object $instance
    */
    protected static function addInstances($aliases, $manager, $instance)
    {
        foreach ($aliases as $alias) {
            $proxy = __NAMESPACE__.'\\'.$alias;
            $manager->addProxyInstance($alias, $proxy, $instance);
        }
    }

    /**
    * Adds services to the Statical Manager
    *
    * @param \RunBB\Core\Statical\Manager $manager
    * @param \Slim\App $slim
    */
    protected static function addServices($manager, $slim)
    {
        $services = [
            'Input' => 'request',
            'Request' => 'request',
            'Response' => 'response',
            'View' => 'template',
            'Menu' => 'menu',
            'Url' => 'url'
        ];

        $container = $slim->getContainer();

        foreach ($services as $alias => $id) {
            $proxy = __NAMESPACE__.'\\'.$alias;
            $manager->addProxyService($alias, $proxy, $container, $id);
        }
    }
}
