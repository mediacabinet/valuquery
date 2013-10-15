<?php
namespace ValuQuery\Selector\SimpleSelector;

use ValuQuery\Selector\SimpleSelector\Attribute;
use ValuQuery\Selector\SimpleSelector\AbstractSelector;

class Path extends AbstractSelector
{
    
    /**
     * Path separator
     * 
     * @var string
     */
    const PATH_SEPARATOR = '/';
    
    protected $name = AbstractSelector::SELECTOR_PATH;
    
    /**
     * Array of path items
     * 
     * @var array
     */
    protected $items;
    
    public function __construct(array $items)
    {
        $this->items = $items;
    }
    
    public function getPathItems(){
        return $this->items;
    }
    
    public function getRawValue(){
        return implode(self::PATH_SEPARATOR, array_map('strval', $this->items));
    }

    public static function getEnclosure()
    {
        return array(self::PATH_SEPARATOR);
    }
}