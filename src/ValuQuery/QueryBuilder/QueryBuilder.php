<?php
namespace ValuQuery\QueryBuilder;

use Zend\EventManager\EventManager;
use ValuQuery\Selector\Selector;
use ValuQuery\Selector\Sequence;
use ValuQuery\Selector\SimpleSelector\SimpleSelectorInterface;
use ValuQuery\QueryBuilder\Event\SimpleSelectorEvent;
use ValuQuery\QueryBuilder\Event\SelectorEvent;
use ValuQuery\QueryBuilder\Event\QueryBuilderEvent;
use ValuQuery\QueryBuilder\Exception\SelectorNotSupportedException;
use ArrayObject;
use ValuQuery\QueryBuilder\Exception\InvalidQueryException;

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
     * @param object $query
     * @return object $query
     */
    public function build(Selector $selector, $query = null)
    {
        $this->assertQueryIsValid($query, true);
        
        $event = new QueryBuilderEvent('prepareQuery', $this);
        $event->setQuery($query);
        $this->getEventManager()->trigger($event);
        
        $this->assertQueryIsValid($event->getQuery(), false);
        
        $this->buildSelector($selector, $event->getQuery());
        
        return $event->getQuery();
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
     * @param mixed $query
     */
    protected function buildSelector(Selector $selector, $query)
    {
        $this->buildSequence($selector->getFirstSequence(), $query);
    }
    
    /**
     * Build sequence
     * 
     * @param Sequence $sequence
     * @param mixed $query
     */
    protected function buildSequence(Sequence $sequence, $query)
    {
        $childSequence = $sequence->getChildSequence();
        $combinator = $sequence->getChildCombinator();
        $evm = $this->getEventManager();
        
        if ($childSequence) {
            $this->buildSequence($childSequence, $query);
        }

        foreach ($sequence as $simpleSelector) {
            $this->buildSimpleSelector($simpleSelector, $query);
        }
        
        if ($childSequence) {
            $args = new ArrayObject([
                'sequence'          => $sequence,
                'childSequence'     => $childSequence
            ]);
            
            $event = new SelectorEvent('combineSequence', $this, $args);
            $event->setQuery($query);
            $evm->trigger($event);
        }
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
        $event->setQuery($query);
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
    
    /**
     * Assert that query is valid
     * 
     * @param mixed $query
     * @param boolean $acceptNull
     * @throws InvalidQueryException
     */
    private function assertQueryIsValid($query, $acceptNull)
    {
        if ($query === null && $acceptNull) {
            return;
        }
        
        if (!is_object($query)) {
            throw new InvalidQueryException(
                sprintf('%s is not valid query container; object expected', gettype($query))
            );
        }
    }
}