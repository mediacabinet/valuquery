<?php
if (
    !($loader = @include_once __DIR__ . '/../../../autoload.php')
) {
    throw new RuntimeException('vendor/autoload.php could not be found. Did you run `php composer.phar install`?');
}

$loader->add('ValuQueryTest', __DIR__);