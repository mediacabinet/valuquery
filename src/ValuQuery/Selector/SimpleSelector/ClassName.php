<?php
namespace ValuQuery\Selector\SimpleSelector;

use ValuQuery\Selector\SimpleSelector\Attribute,
    ValuQuery\Selector\SimpleSelector\AbstractSelector;

class ClassName extends Attribute
{
    
    protected $name = AbstractSelector::SELECTOR_CLASS;
    
    public function __construct($value)
    {
        parent::__construct('class', Attribute::OPERATOR_EQUALS, $value);
    }
    
    public function getPattern(){
        $enclosure = self::getEnclosure();
        
        return array_pop($enclosure) . $this->getCondition();
    }
    
    public static function getEnclosure()
    {
        return array('.');
    }
    
}