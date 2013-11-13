<?php
namespace ValuQueryTest\TestAsset\Filter;

use Doctrine\ODM\MongoDB\Query\Filter\BsonFilter;
use Doctrine\ODM\MongoDB\Mapping\ClassMetaData;

class NonRemovedFilter extends BsonFilter
{
    public function addFilterCriteria(ClassMetadata $targetEntity)
    {
        return ['removed' => false];
    }
}