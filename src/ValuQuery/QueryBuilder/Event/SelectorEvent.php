<?php
namespace ValuQuery\QueryBuilder\Event;

use ValuQuery\Selector\Sequence;

class SelectorEvent extends QueryBuilderEvent
{
    public function setSequence(Sequence $sequence)
    {
        return $this->setParam('sequence', $sequence);
    }
    
    /**
     * @return Sequence
     */
    public function getSequence()
    {
        return $this->getParam('sequence');
    }
    
}