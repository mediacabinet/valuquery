<?php
namespace ValuQuery\DoctrineMongoOdm\Path;

use ValuQuery\Selector\SimpleSelector\Path;

interface ResolverInterface{

    /**
     * Resolve path
     * 
     * @param Path $path
     * @return string Resolved path
     */
    public function resolve(Path $path);
}