<?php
namespace ValuQuery\Selector\Parser;

use ValuQuery\Selector\SimpleSelector,
    ValuQuery\Selector\Parser\AbstractParser;

class PathParser extends AbstractParser
{
    /**
     * Parse pseudo selector from pattern
     *
     * @param string $pattern
     */
    public function parse($pattern)
    {
        $this->setPattern($pattern);
        
        if($this->pattern === ''){
            $items = array();
        }
        else{
            $items = $this->parsePattern();
        }

        $selector = new SimpleSelector\Path(
            $items      
        );
        
        return $selector;
    }
    
    protected function parsePattern()
    {
        // Split by path enclosure
        $enclosure = SimpleSelector\Path::getEnclosure();
        $enclosure = array_pop($enclosure);
        $items     = explode($enclosure, $this->pattern);
        
        // Unescape
        $items = array_map([$this, 'unescape'], $items);
        
        // Valid selectors for first item
        $childSelectorEnclosures = array_merge(
            SimpleSelector\Id::getEnclosure(),
            SimpleSelector\Role::getEnclosure()
        );
        
        if ($items[0] === '') {
            unset($items[0]);
        }
        
        $items = array_values($items);
        
        // Parse first item as a child simple selector
        $this->next();
        if (in_array($this->current(), $childSelectorEnclosures)) {
            $parser = new SimpleSelectorParser();
            $items[0] = $parser->parse($items[0]);
        }
        
        return array_values($items);
    }
}