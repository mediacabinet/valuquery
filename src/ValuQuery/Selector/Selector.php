<?php
namespace ValuQuery\Selector;

class Selector
{
    const COMBINATOR_DESCENDENT = 'descendent';
    const COMBINATOR_CHILD = 'child';
    const COMBINATOR_IMMEDIATE_SIBLING = 'immediate_sibling';
    const COMBINATOR_ANY_SIBLING = 'any_sibling';
    
    /**
     * Root sequence
     * 
     * @var Sequence
     */
    protected $sequence;
    
    public static $combinatorChars = array(
        self::COMBINATOR_DESCENDENT => ' ',
        self::COMBINATOR_CHILD => '>',
        self::COMBINATOR_IMMEDIATE_SIBLING => '+',
        self::COMBINATOR_ANY_SIBLING => '~',
    );
    
    /**
     * Append new sequence to selector
     *  
     * @param Sequence $sequence
     * @param string $combinator Combinator
     * @throws \InvalidArgumentException
     * @throws \Exception
     * 
     * @return Selector
     */
    public function appendSequence(Sequence $sequence, $combinator){
        
        if(!in_array($combinator, array_keys(self::$combinatorChars)) && $combinator !== null){
            throw new \InvalidArgumentException('Invalid combinator specified: '.$combinator);
        }
        
        if($this->sequence && $combinator == null){
            throw new \Exception("Combinator must be defined");
        }
        else if(!$this->sequence && $combinator !== null){
            throw new \Exception("Combinator must be null for the first sequence");
        }
        
        /**
         * Set root sequence
         */
        if(!$this->sequence){
            $this->sequence = $sequence;
        }
        else{
            $this->getLastSequence()
                ->setChildSequence($sequence)
                ->setChildCombinator($combinator);
        }
        
        return $this;
    }
    
    /**
     * Retrieve sequence path
     * 
     * Path is an array of sets where combinator
     * is followed by sequence (except for the first
     * item, which is always a sequence).
     * 
     * @return array
     */
    public function getSequencePath()
    {
        $path     = array();
        $sequence = $this->sequence;
        
        if(!$this->sequence){
            return array();
        }
        
        do{
            $path[] = $sequence;
            
            if($sequence->getChildCombinator()){
                $path[] = $sequence->getChildCombinator(); 
            }
        }
        while(($sequence = $sequence->getChildSequence()) !== null);
        
        return $path;
    }
    
    /**
     * Retrieve sequence at index
     * 
     * @param int $index
     * @return Sequence Sequence or null if not found
     */
    public function getSequence($index){
        $sequence = $this->sequence;
        
        for($i = 0; $sequence !== null; $i++){

            if($i == $index){
                return $sequence;
            }
            
            $sequence = $sequence->getChildSequence();
        }
        
        return null;
    }
    
    /**
     * Retrieve first sequence
     * 
     * @return Sequence|null
     */
    public function getFirstSequence(){
        return $this->sequence;
    }

    /**
     * Retrieve last sequence
     * 
     * @return Sequence|null
     */
    public function getLastSequence(){
        
        $child = $this->sequence;
        
        do{
            $sequence = $child;
        }
        while($sequence && ($child = $sequence->getChildSequence()) !== null);
        
        return $sequence;
    }
    
    /**
     * Remove last sequence
     * 
     * @return Sequence
     */
    public function popSequence(){
        $last = $this->getLastSequence();
        
        if($last === $this->sequence){
            $this->sequence = null;
        }
        else{
            $last->getParentSequence()
                 ->removeChildSequence();
        }
        
        return $last;
    }
    
    /**
     * Remove first sequence
     */
    public function shiftSequence(){
        
        $first = $this->sequence;
        
        if($first){
            $child = $first->getChildSequence();
            $first->removeChildSequence();
            
            $this->sequence = $child;
        }
        
        return $first;
    }
    
    public function __toString(){
        
        $selector = '';
        
        foreach($this->getSequencePath() as $value){
            if(is_object($value)) $selector .= (string) $value;
            else $selector .= self::$combinatorChars[$value];
        }
        
        return $selector;
    }
   
    public function __clone(){
        $this->sequence = clone $this->sequence;
    }
}