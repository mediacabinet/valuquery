<?php
namespace ValuQuery\Selector\Parser;

use ValuQuery\Selector\SimpleSelector\Attribute;

class AttributeSelectorParser extends AbstractParser
{
    
    /**
     * Attribute name
     * 
     * @var string
     */
    protected $attribute = null;
    
    /**
     * Operator type
     * 
     * @var string
     */
    protected $operator = null;
    
    /**
     * Value of attribute
     * 
     * @var string
     */
    protected $value = null;   

    private static $operatorChars = null;
    
    /**
     * Parse attribute selector from pattern
     * 
     * @param string $pattern
     */
    public function parse($pattern){
        
        $this->setPattern($pattern);
        
        // Parse attribute and advance
        if($this->parseAttribute() && $this->valid()){
            
            if($this->parseOperator() && $this->valid()){
                // Parse value
                $this->parseValue();
            }
        }
        
        $selector = new Attribute(
            $this->attribute, 
            $this->operator, 
            $this->value
        );
        
        return $selector;
    }
    
    /**
     * (non-PHPdoc)
     * @see ValuQuery\Selector\Parser.Parser::setPattern()
     */
    protected function setPattern($pattern){
        parent::setPattern($pattern);
        
        $this->attribute = null;
        $this->operator = null;
        $this->value = null;
    }
    
    /**
     * Seek for the beginning of attribute, parse attribute
     * value and place cursor in the next key cursor position
     * 
     * @return string|false
     */
    protected function parseAttribute(){
        
        $operatorChars = self::getOperatorChars();
        
        // Set new cursor location
        $cursor = $this->seekKeyCursorLocation(true, $this->key());
        if($cursor === false) return false;
        
        $this->cursor = $cursor;
        
        //Find operator
        $operatorPosition = $this->findAny(
            $operatorChars,
            $this->key()+1
        );
        
        if($operatorPosition == false){
            $operatorPosition = $this->length;
        }
        
        $attributeLast  = $operatorPosition - 1;
        $attributeLast  = $this->seekKeyCursorLocation(false, $attributeLast);
        
        // Set attribute
        $this->attribute = substr($this->pattern, 0, $attributeLast+1);
        
        // Set cursor position to operator position
        $this->cursor = $operatorPosition;
        
        return true;
    }
    
    /**
     * Parse operator from the current cursor position and advance
     * the cursor to the next character
     *
     * @return string|false
     */
    protected function parseOperator(){
        
        $supportedOperators = Attribute::getSupportedOperators();

        if(in_array($this->current(), $supportedOperators)){
            $operator = $this->current();
            $this->next();
        }
        else{
            $operator = $this->current();
            $this->next();
        }
        
        if($this->current() == Attribute::OPERATOR_EQUALS){
            $operator .= $this->current();
            $this->next();
        }
        else if(!in_array($operator, $supportedOperators)){
            throw new \Exception(sprintf('Invalid operator "%s" provided for pattern "%s"', $operator, $this->pattern));
        }
        
        $this->operator = $operator;
        
        return true;
    }
    
    /**
     * Seek for the value beginning from the next key cursor
     * position
     *
     * @return string|false
     */
    protected function parseValue(){
        
        $cursor = $this->seekKeyCursorLocation(true, $this->key());
        $quoted = false;
        
        // Update cursor position to next key location
        if($cursor === false) return false;
        else $this->cursor = $cursor;
        
        // Find value withing quotes
        if($this->cursorAtQuote()){
            $quoted = true;
            $quote = $this->current();
            $this->next();
            
            $valueLast = $this->findChar($quote);
        
            if($valueLast === false){
                return false;
            }
            else{
                $valueLast--;
            }
        }
        // Value not enclosed in quotes, fetch everything
        else{
            // Seek backwards, starting from the end of string
            $valueLast = $this->seekKeyCursorLocation(false, $this->length-1);
        }
        
        // Fetch value
        $value = substr(
            $this->pattern,
            $this->key(),
            ($valueLast - $this->key() + 1)
        );
        
        // Convert some special strings to corresponding
        // primitive types
        if(!$quoted){
            if($value == 'true' || $value == 'TRUE'){
                $value = true;
            }
            else if($value == 'false' || $value == 'FALSE'){
                $value = false;
            }
            else if($value == 'null' || $value == 'NULL'){
                $value = null;
            }
            else if(is_numeric($value)){
                if(strstr($value, '.') == false) $value = intval($value);
                else $value = floatval($value);
            }
        }
        
        $this->value = $value;
        
        return true;
    }
    
    /**
     * Get characters reserved for operators
     * 
     * @return array
     */
    protected static function getOperatorChars(){
        if(self::$operatorChars == null){
            $operators = Attribute::getSupportedOperators();
            
            self::$operatorChars = array();
            
            foreach($operators as $value){
                self::$operatorChars[] = $value[0];
            }
        }
        
        return self::$operatorChars;
    }
}