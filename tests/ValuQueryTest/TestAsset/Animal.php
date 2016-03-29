<?php
namespace ValuQueryTest\TestAsset;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(repositoryClass="ValuQueryTest\TestAsset\AnimalRepository")
 * @ODM\InheritanceType("SINGLE_COLLECTION")
 * @ODM\DiscriminatorField(fieldName="type")
 * @ODM\DiscriminatorMap({"dog"="ValuQueryTest\TestAsset\Dog", "cat"="ValuQueryTest\TestAsset\Cat", "fish"="ValuQueryTest\TestAsset\Fish"})
 */
abstract class Animal{

	/**
	 * @ODM\Id(strategy="UUID")
	 * @var string
	 */
	public $id;

	/**
	 * @ODM\Field(type="string")
	 * @var string
	 */
	public $name;

	/**
	 * @ODM\Field(type="int")
	 * @var int
	 */
	public $maxAge;

	/**
	 * @ODM\Field(type="float")
	 * @var float
	 */
	public $maxWidth;

	/**
	 * @ODM\Field(type="boolean")
	 * @var boolean
	 */
	public $canFly;

	/**
	 * @ODM\Field(type="string")
	 * @var string
	 */
	public $path;

	/**
	 * @ODM\Boolean(name="breedUw")
	 * @var string
	 */
	public $isAbleToBreedUnderWater;

	/**
	 * Classes
	 *
	 * @ODM\Field(type="collection")
	 * @param array
	 */
	public $classes = array();

	/**
	 * Classes
	 *
	 * @ODM\Field(type="collection")
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
	 * @ODM\ReferenceOne(targetDocument="Animal")
	 * @var Animal
	 */
	public $root;

	/**
	 * @ODM\ReferenceOne(targetDocument="Animal")
	 * @var Animal
	 */
	public $parent;

    /**
	 * @ODM\ReferenceOne(targetDocument="Sound", simple=true)
	 * @var Sound
	 */
	public $sound;

	/**
	 * @ODM\Field(type="date")
	 * @var \DateTime
	 */
	public $createdAt;
}
