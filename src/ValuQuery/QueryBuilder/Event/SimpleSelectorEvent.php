<?php
namespace ValuQuery\QueryBuilder\Event;

use ValuQuery\Selector\SimpleSelector\SimpleSelectorInterface;

class SimpleSelectorEvent extends QueryBuilderEvent
{
    
    /**
     * @var SimpleSelectorInterface
     */
    protected $simpleSelector;
    
    public function __construct(SimpleSelectorInterface $simpleSelector, $query, $target = null, $params = null)
    {
        parent::__construct('apply'.ucfirst($simpleSelector->getName()).'Selector', $target, $params);
        $this->setQuery($query);
        $this->setSimpleSelector($simpleSelector);
    }
    
	/**
     * @return \ValuQuery\Selector\SimpleSelector\SimpleSelectorInterface
     */
    public function getSimpleSelector()
    {
        return $this->simpleSelector;
    }

	/**
     * @param \ValuQuery\Selector\SimpleSelector\SimpleSelectorInterface $simpleSelector
     */
    public function setSimpleSelector(SimpleSelectorInterface $simpleSelector)
    {
        $this->simpleSelector = $simpleSelector;
    }

}