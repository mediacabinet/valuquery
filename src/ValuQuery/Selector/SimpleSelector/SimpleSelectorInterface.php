<?php
namespace ValuQuery\Selector\SimpleSelector;

interface SimpleSelectorInterface
{
    /**
     * Retrieve selector name
     * 
     * @return string
     */
    public function getName();
    
    /**
     * Retrieve raw selector value
     *
     * @return string
     */
    public function getEscapedValue();
    
    /**
     * Retrieve selector pattern
     * 
     * @return string
     */
    public function getPattern();
    
    /**
     * Retrieve selector enclosure
     * 
     * @return array|string
     */
    public static function getEnclosure();
}