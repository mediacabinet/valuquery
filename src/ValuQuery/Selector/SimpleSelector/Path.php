<?php
namespace ValuQuery\Selector\SimpleSelector;

use ValuQuery\Selector\SimpleSelector\AbstractSelector;
use ValuQuery\Selector\Selector;

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
    
    /**
     * Retrieve path as a string
     * 
     * @return string
     */
    public function getPath()
    {
        $components = array_map('strval', $this->items);
        return implode(self::PATH_SEPARATOR, $components);
    }
    
    public function getEscapedValue(){
        $components = array_map('strval', $this->items);
        $components = array_map('\ValuQuery\Selector\SimpleSelector\Path::escapePathComponent', $components);
        
        return implode(self::PATH_SEPARATOR, $components);
    }
    
    /**
     * @return SimpleSelectorInterface|null
     */
    public function getRootSelector()
    {
        if (sizeof($this->items) && $this->items[0] instanceof SimpleSelectorInterface) {
            return $this->items[0];
        } else {
            return null;
        }
    }

    public static function getEnclosure()
    {
        return array(self::PATH_SEPARATOR);
    }
    
    /**
     * Escape path component
     * 
     * @param string $component
     * @return string Escaped path component
     */
    public static function escapePathComponent($component)
    {
        $toBeEscaped = implode('', Selector::$combinatorChars); // Escape sequence combinators
        $toBeEscaped .= '[:'; // Escape attribute and pseudo selector characters
        
        return preg_replace('/(['.$toBeEscaped.'])/', '\\\$1', $component);
    }
}