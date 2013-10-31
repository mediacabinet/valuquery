<?php
namespace ValuQuery\Selector\Parser;

use ValuQuery\Selector;

class SelectorParser extends AbstractParser
{
    protected $combinators = array(
        ' ' => Selector\Selector::COMBINATOR_DESCENDENT,
        '>' => Selector\Selector::COMBINATOR_CHILD,
        '+' => Selector\Selector::COMBINATOR_IMMEDIATE_SIBLING,
        '~' => Selector\Selector::COMBINATOR_ANY_SIBLING,
    );
    
    public function parse($pattern)
    {
        $this->setPattern($pattern);
    
        $seqParser   = $this->getSequenceParser();
        $selector    = new Selector\Selector();
        $combinator  = null;
        $seekOffset  = 0;   
    
        do{
            // Fetch current combinator
            $combinator = isset($this->combinators[$this->current()]) ? 
                $this->combinators[$this->current()] 
                : null;
    
            // Set new cursor location
            $cursor = $this->seekKeyCursorLocation(true, $this->key());
            if($cursor === false) break;
    
            $this->cursor = $cursor;
    
            // Override with non-whitespace-combinator, if found
            if ($combinator == Selector\Selector::COMBINATOR_DESCENDENT) {
                $nonWhiteSpaceCombinator = isset($this->combinators[$this->current()]) ? 
                    $this->combinators[$this->current()] 
                    : false;
    
                if ($nonWhiteSpaceCombinator !== false && $nonWhiteSpaceCombinator !== $combinator) {
                    $combinator = $nonWhiteSpaceCombinator;
                }
            }
    
            // Move to next valid location after combinator
            if ($combinator !== null 
                && $combinator != Selector\Selector::COMBINATOR_DESCENDENT) {
                $this->nextKeyChar();
            }
    
            // Sequence ends at the next combinator or end of the string
            $seqLast = $this->findAny(array_keys($this->combinators), $this->key()+$seekOffset);
    
            if ($seqLast === false || $seqLast > $this->length) {
                $seqLast = $this->length-1;
            } else{
                $seqLast--;
            }
    
            // Start new sequence
            $seqPattern = substr(
                $this->pattern,
                $this->key(),
                ($seqLast-$this->key()+1)
            );
            
            $sequence = $seqParser->parse(
                $seqPattern        
            );
    
            // Add sequence to selector, using combinator
            $selector->appendSequence($sequence, $combinator);
    
            // Update cursor location to combinator (or end of string)
            $this->cursor   = $seqLast + 1;
            $seekOffset     = 1;
        }
        while($this->valid());
    
        return $selector;
    }
    
    protected function getSequenceParser()
    {
        return new SequenceParser();
    }
    
    /**
     * Parse selector
     * 
     * @param string $pattern
     * @return ValuQuery\SelectorParser\Selector
     */
    public static function parseSelector($pattern)
    {
        $parser = new SelectorParser();
        return $parser->parse($pattern);
    }
}