<?php
namespace ValuQueryTest\TestAsset;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document
 * @ODM\InheritanceType("SINGLE_COLLECTION")
 * @ODM\DiscriminatorField(fieldName="type")
 * @ODM\DiscriminatorMap({"dog"="ValuQueryTest\TestAsset\Dog", "cat"="ValuQueryTest\TestAsset\Cat"})
 */
abstract class Animal{
 
	/**
	 * @ODM\Id(strategy="UUID")
	 * @var string
	 */
	public $id;

	/**
	 * @ODM\String
	 * @var string
	 */
	public $name;
	
	/**
	 * @ODM\Int
	 * @var int
	 */
	public $maxAge;
	
	/**
	 * @ODM\Float
	 * @var float
	 */
	public $maxWidth;
	
	/**
	 * @ODM\Boolean
	 * @var boolean
	 */
	public $canFly;
	
	/**
	 * @ODM\String
	 * @var string
	 */
	public $path;

	/**
	 * Classes
	 * 
	 * @ODM\Collection
	 * @param array
	 */
	public $classes = array();
	
	/**
	 * Classes
	 *
	 * @ODM\Collection
	 * @param array
	 */
	public $roles = array();
	
	/**
	 * @ODM\EmbedOne(targetDocument="Organ")
	 * @var Organ
	 */
	public $head;
	
	/**
	 * @ODM\EmbedMany(targetDocument="Organ")
	 * @var ArrayCollection
	 */
	public $limbs;
	
	/**
	 * @ODM\ReferenceMany(targetDocument="Animal")
	 * @var ArrayCollection
	 */
	public $related;
	
	/**
	 * @ODM\ReferenceOne(targetDocument="Animal", simple=true)
	 * @var ArrayCollection
	 */
	public $root;
	
	/**
	 * @ODM\Date
	 * @var \DateTime
	 */
	public $createdAt;
}