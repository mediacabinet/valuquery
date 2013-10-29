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
	 * @ODM\String
	 * @var string
	 */
	public $name;
	
	/**
	 * @ODM\Boolean
	 * @var boolean
	 */
	public $replacable = false;
}