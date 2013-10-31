<?php
namespace ValuQuery\Selector\SimpleSelector;

use ValuQuery\Selector\SimpleSelector\AbstractSelector;

class Element extends AbstractSelector
{
    /**
     * Selector name
     * 
     * @var string
     */
    protected $name = AbstractSelector::SELECTOR_ELEMENT;
    
    /**
     * Element name
     * 
     * @var string
     */
    protected $element;
    
    public function __construct($element)
    {
        $this->element = $element;
    }
    
    /**
     * Retrieve element name
     * 
     * @return string
     */
    public function getElement()
    {
        return $this->element;
    }
    
    /**
     * @see \ValuQuery\Selector\SimpleSelector\SimpleSelectorInterface::getEscapedValue()
     */
    public function getEscapedValue()
    {
        return $this->getElement();
    }
    
    public static function getEnclosure()
    {
        return array('');
    }
}