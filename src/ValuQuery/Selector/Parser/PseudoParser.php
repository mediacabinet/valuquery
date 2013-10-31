<?php
namespace ValuQuery\Selector\Parser;

use ValuQuery\Selector\SimpleSelector\Pseudo;
use ValuQuery\Selector\Parser\Exception\InvalidPatternException;

class PseudoParser extends AbstractParser
{
    
    /**
     * Pseudo class name
     * 
     * @var string
     */
    protected $className;
    
    /**
     * Pseudo class value
     * 
     * @var string
     */
    protected $value = null;
    
    protected $parsers = array(
        'sort' => 'ValuQuery\Selector\Parser\Pseudo\Sort'        
    );
    
    /**
     * Parse pseudo selector from pattern
     *
     * @param string $pattern
     */
    public function parse($pattern){
    
        $this->setPattern($pattern);
    
        // Parse attribute and advance
        if($this->parseClassName() && $this->valid()){
            $this->parseValue();
        }
        
        if(!$this->className){
            throw new InvalidPatternException(
                'Pseudo-class pattern "'.$pattern.'" is not valid');
        }
        
        if(isset($this->parsers[$this->className])){
            $parser = new $this->parsers[$this->className];
            return $parser->parse($this->value);
        }
        else{
            $selector = new Pseudo(
                $this->className,
                $this->unescape($this->value)
            );
        }
    
        return $selector;
    }
    
    /**
     * Seek for the beginning of attribute, parse attribute
     * value and place cursor in the next key cursor position
     *
     * @return string|false
     */
    protected function parseClassName(){
    
        // Find enclosing character for value
        $encloser = $this->findChar('(');
    
        if($encloser == false){
            $encloser = $this->length;
        }
    
        // Fetch class name
        $last = $this->seekKeyCursorLocation(false, $encloser - 1);
        $this->className = substr($this->pattern, 0, $last+1);
    
        // Set cursor position to encloser position
        $this->cursor = $encloser;
    
        return true;
    }
    
    protected function parseValue(){
        
        // Find next valid character
        $cursor = $this->seekKeyCursorLocation(true, $this->key()+1);
        $this->cursor = $cursor;
        
        // Find closing enclosing character for value
        $encloser = $this->findChar(')');
        
        if($encloser == false){
            throw new InvalidPatternException(
                'Missing closing encloser character ")" for pseudo-class pattern');
        }
        
        // Fetch value
        $last = $this->seekKeyCursorLocation(false, $encloser - 1);
        $this->value = substr($this->pattern, $this->cursor, ($last+1)-$this->cursor);
        
        // Set cursor position to encloser position
        $this->cursor = $encloser;
    }
}