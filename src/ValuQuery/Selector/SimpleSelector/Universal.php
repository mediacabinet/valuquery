<?php
namespace ValuQuery\Selector\SimpleSelector;

use ValuQuery\Selector\SimpleSelector\AbstractSelector;

class Universal extends AbstractSelector
{
    protected $name = AbstractSelector::SELECTOR_UNIVERSAL;
    
    public function getEscapedValue()
    {
        return '';
    }

    public static function getEnclosure()
    {
        return array('*');
    }
}