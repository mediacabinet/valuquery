<?php
namespace ValuQueryTest\TestAsset;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\EmbeddedDocument
 */
class Organ{
 
	/**
	 * @ODM\Id
	 * @var string
	 */
	public $id;

	/**
	 * @ODM\Field(type="string")
	 * @var string
	 */
	public $name;
	
	/**
	 * @ODM\Field(type="boolean")
	 * @var boolean
	 */
	public $replacable = false;
	
	/**
	 * @ODM\Boolean(name="isInMainCirc")
	 * @var string
	 */
	public $isPartOfMainBloodCirculation = true;
}
