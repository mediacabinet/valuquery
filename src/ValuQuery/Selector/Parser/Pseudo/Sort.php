<?php
namespace ValuQuery\Selector\Parser\Pseudo;

use ValuQuery\Selector\SimpleSelector,
    ValuQuery\Selector\Parser\AbstractParser;

class Sort extends AbstractParser
{
    
    /**
     * Parse pseudo selector from pattern
     *
     * @param string $pattern
     */
    public function parse($pattern){
        
        $this->setPattern($pattern);
        
        // Split by whitespace
        $specs = explode(' ', $this->pattern);
        
        $attribute = null;
        $order = 'asc';
        
        foreach($specs as $value){
            
            if($value == ' '){
                continue;
            }
            
            // Attribute name
            if($attribute === null){
                $attribute = trim($value, '"');
                continue;
            }
            
            // Sort order
            if($attribute !== null){
                $order = strtolower($value);
                break;
            }
        }
        
        if(!in_array($order, array('asc', 'desc'))){
            throw new \Exception('Invalid sort order, asc or desc expected');
        }
        
        $selector = new SimpleSelector\Pseudo\Sort(
            $attribute,
            $order      
        );
        
        return $selector;
    }
}