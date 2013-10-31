<?php
/*
* Set error reporting to the level to which VALU code must comply.
*/
error_reporting( E_ALL | E_STRICT );

// Setup autoloading
if (!($loader = @include_once __DIR__ . '/../vendor/autoload.php')) {
    throw new RuntimeException('vendor/autoload.php could not be found. Did you run `php composer.phar install`?');
}

$loader->add('ValuQueryTest', __DIR__);