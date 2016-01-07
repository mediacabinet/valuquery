<?php
namespace ValuQueryTest\TestAsset;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\EmbeddedDocument
 */
class Sound{

	/**
	 * @ODM\Id(strategy="UUID")
	 * @var string
	 */
	public $id;

	/**
	 * @ODM\String
	 * @var string
	 */
	public $says;
}
