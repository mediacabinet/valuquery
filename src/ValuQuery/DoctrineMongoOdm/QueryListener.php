<?php
namespace ValuQuery\DoctrineMongoOdm;

use ValuQuery\MongoDb\QueryListener as BaseListener;

class QueryListener extends BaseListener
{
    public function applyElementSelector(SimpleSelectorEvent $event)
    {
        $elementSelector = $event->getSimpleSelector();
        $document = $this->getDocumentNameForElement($elementSelector->getValue());
    
        $event->setQuery(
                $this->createQueryBuilder($document)->expr()
        );
    }
}