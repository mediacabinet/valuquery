<?php
namespace ValuQueryTest\TestAsset;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document
 */
class Dog
    extends Animal
{
    /**
     * @ODM\Field(type="string")
     * @var string
     */
    public $barkSound = 'whuf!';
}
