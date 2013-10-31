<?php
namespace ValuQuery\Selector;

use ValuQuery\Selector\SimpleSelector\Universal;
use ValuQuery\Selector\SimpleSelector\Element;
use ValuQuery\Selector\SimpleSelector\Path;
use ValuQuery\Selector\SimpleSelector\SimpleSelectorInterface;

class Sequence implements \Iterator
{

    /**
     * Array of items in sequence
     *
     * @var array
     */
    protected $items = array();

    /**
     * Child sequence
     *
     * @var Sequence
     */
    protected $childSequence;

    /**
     * Child combinator
     *
     * @var string
     */
    protected $childCombinator;

    /**
     * Parent sequence
     *
     * @var Sequence
     */
    protected $parentSequence;

    private $position = 0;

    /**
     * Append new simple selector
     *
     * @param SimpleSelector $simpleSelector            
     * @return Sequence
     */
    public function appendSimpleSelector(SimpleSelectorInterface $simpleSelector)
    {
        
        /**
         * Prepend Universal selector if no Element or Universal
         * selector is applied
         */
        /*
         * if( !sizeof($this->items) && !($simpleSelector instanceof Universal)
         * && !($simpleSelector instanceof Element)){ $universal = new
         * Universal(); $this->appendSimpleSelector($universal); }
         */
        
        /**
         * Ensure that Universal and Element selectors are allowed
         * to appear only in the beginning of the sequence
         */
        if (sizeof($this->items) 
            && (($simpleSelector instanceof Universal) 
               || ($simpleSelector instanceof Element) 
               || ($simpleSelector instanceof Path))) {
            
            throw new \InvalidArgumentException(
                'Universal, Element and Path selectors must appear first in the sequence');
        }
        
        $this->items[] = $simpleSelector;
        return $this;
    }

    /**
     * Is the sequence universal?
     *
     * @return boolean
     */
    public function isUniversal()
    {
        return (!($this->items[0] instanceof Element));
    }

    /**
     * Is the sequence defined explicitely universal?
     * 
     * @return boolean
     */
    public function isExplicitUniversal()
    {
        return ($this->items[0] instanceof Universal);
    }
    
    /**
     * Turns universal sequence into element specific
     * sequence
     *
     * @param string $element            
     * @return SimpeSelector null
     */
    public function setElement($element)
    {
        $current = isset($this->items[0]) ? $this->items[0] : null;
        $selector = new Element($element);
        $this->items[0] = $selector;
        
        return $current;
    }

    /**
     * Retrieve element name for this sequence
     *
     * @return string null
     */
    public function getElement()
    {
        if (sizeof($this->items) && $this->items[0] instanceof Element) {
            return $this->items[0]->getElement();
        } else {
            return null;
        }
    }

    /**
     * Turns element specific sequence into universal
     *
     * @return SimpeSelector null
     */
    public function setUniversal()
    {
        $current = isset($this->items[0]) ? $this->items[0] : null;
        $this->items[0] = new Universal();
        
        return $current;
    }

    /**
     * Set child sequence
     *
     * @param Sequence $sequence            
     * @return \ValuQuery\Selector\Sequence
     */
    public function setChildSequence(Sequence $sequence)
    {
        $this->childSequence = $sequence;
        $sequence->setParentSequence($this);
        
        return $this;
    }

    public function setChildCombinator($combinator)
    {
        $this->childCombinator = $combinator;
        return $this;
    }

    public function getChildSequence()
    {
        return $this->childSequence;
    }

    public function getChildCombinator()
    {
        return $this->childCombinator;
    }

    public function getParentSequence()
    {
        return $this->parentSequence;
    }

    public function removeChildSequence()
    {
        if ($this->childSequence) {
            $this->childSequence->removeParentSequence();
        }
        
        $this->childCombinator = null;
        $this->childSequence = null;
    }

    public function popItem()
    {
        return array_pop($this->items);
    }

    public function shiftItem()
    {
        return array_shift($this->items);
    }

    /**
     * Retrieve items in sequence in order of appearance
     * of appearance
     *
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Retrieve item at index
     *
     * @param int $index            
     * @return SimpleSelector null
     */
    public function getItem($index)
    {
        return isset($this->items[$index]) ? $this->items[$index] : null;
    }

    /**
     * Retrieve string representation of sequence
     *
     * @return string
     */
    public function __toString()
    {
        $sequence = '';
        
        foreach ($this->items as $item) {
            $sequence .= (string) $item;
        }
        
        return $sequence;
    }

    /**
     * Retrieve number of simple selectors in sequence
     *
     * @return int
     */
    public function count()
    {
        return sizeof($this->items);
    }

    /**
     * (non-PHPdoc)
     * 
     * @see Iterator::current()
     */
    public function current()
    {
        return $this->items[$this->position];
    }

    /**
     * (non-PHPdoc)
     * 
     * @see Iterator::key()
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * (non-PHPdoc)
     * 
     * @see Iterator::next()
     */
    public function next()
    {
        ++ $this->position;
    }

    /**
     * (non-PHPdoc)
     * 
     * @see Iterator::rewind()
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * (non-PHPdoc)
     * 
     * @see Iterator::valid()
     */
    public function valid()
    {
        return isset($this->items[$this->position]);
    }

    public function __clone()
    {
        if ($this->childSequence) {
            $this->setChildSequence(clone $this->childSequence);
        }
    }

    /**
     * Parent sequence
     *
     * @param Sequence $sequence            
     * @return Sequence
     */
    protected function setParentSequence(Sequence $sequence)
    {
        $this->parentSequence = $sequence;
        return $this;
    }

    /**
     * Remove parent sequence
     *
     * @return Sequence
     */
    protected function removeParentSequence()
    {
        $this->parentSequence = null;
        return $this;
    }
}