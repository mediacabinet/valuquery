<?php
namespace ValuQueryTest\TestAsset;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(repositoryClass="ValuQueryTest\TestAsset\CategoryRepository")
 */
class Category{
 
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
	 * @ODM\ReferenceMany(targetDocument="Category")
	 * @var ArrayCollection
	 */
	public $parent;
	
	/**
	 * @ODM\Field(type="string")
	 * @var string
	 */
	public $path;
	
	/**
	 * Classes
	 *
	 * @ODM\Field(type="collection")
	 * @param array
	 */
	public $roles = array();
}
