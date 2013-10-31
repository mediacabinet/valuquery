<?php
namespace ValuQuery\Selector\SimpleSelector;

use ValuQuery\Selector\SimpleSelector\Attribute;
use ValuQuery\Selector\SimpleSelector\AbstractSelector;

class Id extends Attribute
{

    protected $name = AbstractSelector::SELECTOR_ID;

    public function __construct($value)
    {
        parent::__construct('id', Attribute::OPERATOR_EQUALS, $value);
    }

    public function getEscapedValue()
    {
        return $this->getCondition();
    }

    public static function getEnclosure()
    {
        return array(
            '#'
        );
    }
}