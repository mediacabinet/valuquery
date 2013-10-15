<?php
namespace ValuQuery\Selector\SimpleSelector;

interface SimpleSelectorInterface
{
    const ACCEPT_ALL = 'all';
    
    const ACCEPT_NONE = 'none';
    
    /**
     * Retrieve selector name
     * 
     * @return string
     */
    public function getName();
    
    /**
     * Retrieve selector value
     *
     * @return string
     */
    public function getValue();
    
    /**
     * Retrieve raw selector value
     *
     * @return string
     */
    public function getRawValue();
    
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