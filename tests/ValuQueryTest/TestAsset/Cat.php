<?php
namespace ValuQueryTest\TestAsset;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document
 */
class Cat
    extends Animal
{
    /**
     * @ODM\String
     * @var string
     */
    public $murrSound = 'murrrrr';
}