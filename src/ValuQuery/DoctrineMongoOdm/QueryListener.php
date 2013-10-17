<?php
namespace ValuQuery\DoctrineMongoOdm;

use ValuQuery\QueryBuilder\Event\SimpleSelectorEvent;
use ValuQuery\QueryBuilder\Event\SequenceEvent;
use ValuQuery\MongoDb\QueryListener as BaseListener;
use Doctrine\ODM\MongoDB\Types\Type;
use Doctrine\ODM\MongoDB\DocumentManager;

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
    
    public function applyIdSelector(SimpleSelectorEvent $event)
    {
        $idSelector = $event->getSimpleSelector();
        $element = $this->requireElement();
        $class = $this->getElementMetadata($element);
        $field = $class->getIdentifier();
    
        if ($field) {
    
            if ($class->isIdGeneratorAuto()) {
                $condition = new \MongoId($idSelector->getCondition());
            } else {
                // TODO: might it be that there are user-generated MongoIds as
                // well?
                $condition = $idSelector->getCondition();
            }
    
            $selector = new SimpleSelector\Attribute(
                    $field,
                    SimpleSelector\Attribute::OPERATOR_EQUALS,
                    $condition);
    
            $this->doApplyAttributeSelector($selector, $event->getQuery());
    
            return true;
        }
        return false;
    }
    
    public function applyPathSelector(SimpleSelectorEvent $event)
    {
        $pathSelector = $event->getSimpleSelector();
        
        if (sizeof($pathSelector->getPathItems())) {
            $path = $this->translatePathArray($pathSelector->getPathItems());
            
            // Apply expression that causes this query to return null
            if (! $path) {
                $selector = new SimpleSelector\Attribute(
                    '_id', 
                    SimpleSelector\Attribute::OPERATOR_EQUALS, 
                    false);
                
                return $this->doApplyAttributeSelector($selector, $event->getQuery());
            }
            
            $condition = '^' . SimpleSelector\Path::PATH_SEPARATOR .
                         implode(SimpleSelector\Path::PATH_SEPARATOR, $path) . 
                         '$';
            
            $selector = new SimpleSelector\Attribute(
                $this->getOption('path_attribute'), 
                SimpleSelector\Attribute::OPERATOR_REG_EXP, 
                $condition);
        } else {
            // Query root
            $selector = new SimpleSelector\Attribute(
                $this->getOption('path_attribute'), 
                SimpleSelector\Attribute::OPERATOR_EQUALS, 
                SimpleSelector\Path::PATH_SEPARATOR);
        }
        
        return $this->doApplyAttributeSelector($selector, $event->getQuery());
    }
    
    /**
     * Retrieve current document manager instance
     *
     * @return DocumentManager
     */
    protected function getDocumentManager()
    {
        return $this->documentManager;
    }

    protected function createQueryBuilder($documentName = null)
    {
        return $this->getDocumentManager()->createQueryBuilder($documentName);
    }

    /**
     * Retrieve current document names in an associative
     * array where each key is a name of an element and
     * value corresponding class name
     *
     * @return array
     */
    protected function getDocumentNames()
    {
        return $this->getSequence()->getDocumentNames();
    }
    
    protected function getDocumentNameForElement($element)
    {
        $names = $this->getDocumentNames();
        return isset($names[$element]) ? $names[$element] : null;
    }

    /**
     * Retrieve meta data for class represented by element
     *
     * @param string $element            
     * @return \Doctrine\ODM\MongoDB\Mapping\ClassMetadata
     */
    protected function getElementMetadata($element)
    {
        $className = $this->getDocumentNameForElement($element);
        
        if (!$className) {
            throw new \Exception(sprintf('Unknown document %s', $element));
        }
        
        return $this->getDocumentManager()->getClassMetadata($className);
    }

    /**
     * Map attribute name to corresponding
     * field name in MongoDB
     *
     * @param string $attr            
     * @return string
     */
    protected function mapAttribute($attr)
    {
        $map = $this->getOption('attribute_map');
    
        /**
         * Map attribute name
        */
    
        if(array_key_exists($attr, $map)){
            $attr = $map[$attr];
        }
    
        return $attr;
    }
    
    /**
     * Template method for path translation
     *
     * @param array $items
     *            Path items
     * @return array
     * @throws \Exception
     */
    protected function translatePathArray(array $items)
    {
        if (! sizeof($items)) {
            return array();
        } elseif ($items[0] instanceof Selector) {
            
            $documentName = $this->getOption('path_document', 
                    $this->getDocument());
            
            // Create a new Query Builder instance
            $qb = $this->getDocumentManager()->createQueryBuilder($documentName);
            
            $attr = $this->mapAttribute($this->getOption('path_attribute'));
            
            // Select path attribute
            $qb->select($attr)->hydrate(false);
            
            // Use template to create a new selector query
            $template = $this->getSequence()->getSelectorTemplate();
            $selector = $template->createSelector($items[0]);
            $selector->setDocumentNames(
                    array(
                            Sequence::DEFAULT_ELEMENT => $documentName
                    ));
            $selector->extendQuery($qb);
            
            // Fetch data
            $result = $qb->limit(1)
                ->getQuery()
                ->getSingleResult();
            
            // Exit early if selector could not be resolved
            if (! $result || ! isset($result[$attr]) || ! $result[$attr]) {
                return false;
            }
            
            $path = ltrim($result[$attr], '/');
            
            unset($items[0]);
            $items = array_map('stripslashes', $items);
            
            $items = array_merge(
                    explode(SimpleSelector\Path::PATH_SEPARATOR, $path), 
                    array_values($items));
        } else {
            $items = array_map('stripslashes', $items);
        }
        
        // Convert string items to reg exp
        foreach ($items as &$value) {
            
            if (is_string($value)) {
                $value = preg_quote($value, '/');
                $value = str_replace('\*', '.*', $value);
            }
        }
        
        return $items;
    }
}