<?php
/**
 * Valu FileSystem Module
 *
 * @copyright Copyright (c) 2012-2013 Media Cabinet (www.mediacabinet.fi)
 * @license   BSD 2 License
 */
return function ($class) {
    static $map = null;
    
    if (!$map) {
        $map = include __DIR__ . '/autoload_classmap.php';
    }

    if (!isset($map[$class])) {
        return false;
    }
    return include $map[$class];
};