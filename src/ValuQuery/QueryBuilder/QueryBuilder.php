<?php
namespace ValuQuery\QueryBuilder;

use Zend\EventManager\EventManager;
use ValuQuery\Selector\Selector;
use ValuQuery\Selector\Sequence;
use ValuQuery\Selector\SimpleSelector\SimpleSelectorInterface;
use ValuQuery\QueryBuilder\Event\SimpleSelectorEvent;
use ValuQuery\QueryBuilder\Event\SelectorEvent;
use ArrayObject;
use ValuQuery\QueryBuilder\Event\SequenceEvent;
use ValuQuery\QueryBuilder\Exception\SelectorNotSupportedException;

class QueryBuilder
{
    /**
     * @var EventManager
     */
    protected $eventManager;
    
    /**
     * Build a new query based on given selector
     * 
     * @param Selector $selector
     */
    public function build(Selector $selector)
    {
        $this->buildSelector($selector);
    }
    
    /**
     * Retrieve event manager instance
     * 
     * @return \Zend\EventManager\EventManager
     */
    public function getEventManager()
    {
        if (!$this->eventManager) {
            $this->setEventManager(new EventManager());
        }
        
        return $this->eventManager;
    }
    
    /**
     * Set event manager
     * 
     * @param EventManager $eventManager
     */
    public function setEventManager(EventManager $eventManager)
    {
        $this->eventManager = $eventManager;
    }
    
    /**
     * Build selector
     * 
     * @param Selector $selector
     */
    protected function buildSelector(Selector $selector)
    {
        $this->buildSequence($selector->getFirstSequence());
    }
    
    /**
     * Build sequence
     * 
     * @param Sequence $sequence
     */
    protected function buildSequence(Sequence $sequence)
    {
        $childSequence = $sequence->getChildSequence();
        $combinator = $sequence->getChildCombinator();
        $evm = $this->getEventManager();
        
        if ($childSequence) {
            $this->buildSequence($childSequence);
        }
        
        $event = new SequenceEvent('prepareSequence', $this);
        $evm->trigger($event);
        
        foreach ($sequence as $simpleSelector) {
            $this->buildSimpleSelector($simpleSelector, $event->getQuery());
        }
        
        $args = new ArrayObject([
            'sequence'          => $sequence,
            'childSequence'     => $childSequence,
            'combinator'        => $combinator    
        ]);
        
        $event = new SelectorEvent('combineSequence', $this, $args);
        $evm->trigger($event);
    }
    
    /**
     * Build simple selector
     * 
     * @param SimpleSelectorInterface $simpleSelector
     * @param mixed $query
     */
    protected function buildSimpleSelector(SimpleSelectorInterface $simpleSelector, $query)
    {
        $name = $simpleSelector->getName();
        $success = false;
        $evm = $this->getEventManager();
        
        $args = new ArrayObject();
        $event = new SimpleSelectorEvent($simpleSelector, $query, $this, $args);
        $responses = $evm->trigger($event);
        
        if (!$responses->contains(true)) {
            
            foreach ($responses as $response) {
                if ($response instanceof \Exception) {
                    throw $response;
                }
            } 
            
            throw new SelectorNotSupportedException(
                sprintf('%s selector is not supported', ucfirst($simpleSelector->getName()))
            );
        }
        
    }
}