<?php
namespace RunBB\Core\Interfaces;

abstract class SlimSugar extends \RunBB\Core\Statical\BaseProxy
{
    public static $slim;

    public static function __callStatic($name, $args)
    {
        // Enforce named class methods only
        throw new \BadMethodCallException($name.' method not available');
    }
}
