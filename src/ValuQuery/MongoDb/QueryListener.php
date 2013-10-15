<?php
namespace ValuQuery\MongoDb;

use Zend\EventManager\ListenerAggregateInterface;
use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerInterface;
use ValuQuery\QueryBuilder\Event\SimpleSelectorEvent;
use ValuQuery\QueryBuilder\Event\SequenceEvent;
use ValuQuery\Selector\SimpleSelector;
use Doctrine\ODM\MongoDB\Types\Type;
use Doctrine\ODM\MongoDB\DocumentManager;

class QueryListener implements ListenerAggregateInterface
{

    /**
     *
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = array();

    /**
     *
     * @see \Zend\EventManager\ListenerAggregateInterface::attach()
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(
            'prepareSequence', 
            array(
                $this,
                'prepareSequence'
            ));
        
        $this->listeners[] = $events->attach(
            'combineSequence', 
            array(
                $this,
                'combineSequence'
            ));
        
        $this->listeners[] = $events->attach(
            'applyElementSelector', 
            array(
                $this,
                'applyElementSelector'
            ));
        
        $this->listeners[] = $events->attach(
            'applyIdSelector', 
            array(
                $this,
                'applyIdSelector'
            ));
        
        $this->listeners[] = $events->attach(
            'applyRoleSelector', 
            array(
                $this,
                'applyRoleSelector'
            ));
        
        $this->listeners[] = $events->attach(
            'applyClassSelector', 
            array(
                $this,
                'applyClassSelector'
            ));
        
        $this->listeners[] = $events->attach(
            'applyPathSelector', 
            array(
                $this,
                'applyPathSelector'
            ));
        
        $this->listeners[] = $events->attach(
            'applyAttributeSelector', 
            array(
                $this,
                'applyAttributeSelector'
            ));
        
        $this->listeners[] = $events->attach(
            'applyPseudoSelector', 
            array(
                $this,
                'applyPseudoSelector'
            ));
    }

    /**
     * @see \Zend\EventManager\ListenerAggregateInterface::detach()
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $listener) {
            $events->detach($listener);
        }
    }

    public function prepareSequence(SequenceEvent $event)
    {
        $event->setQuery(
            $this->createQueryBuilder()
        );
    }

    public function combineSequence(EventInterface $event)
    {}

    public function applyElementSelector(SimpleSelectorEvent $event)
    {

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

    public function applyRoleSelector(SimpleSelectorEvent $event)
    {
        $roleSelector = $event->getSimpleSelector();
        
        $selector = new SimpleSelector\Attribute(
            $this->getOption('role_attribute'), 
            SimpleSelector\Attribute::OPERATOR_IN_LIST, 
            $roleSelector->getCondition());
        
        return $this->doApplyAttributeSelector($selector, $event->getQuery());
    }

    public function applyClassSelector(SimpleSelectorEvent $event)
    {
        $classSelector = $event->getSimpleSelector();
        $selector = new SimpleSelector\Attribute(
            $this->getOption('class_attribute'), 
            SimpleSelector\Attribute::OPERATOR_IN_LIST, 
            $classSelector->getCondition());
        
        return $this->doApplyAttributeSelector($selector, $event->getQuery());
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

    public function applyAttributeSelector(SimpleSelectorEvent $event)
    {
        $attrSelector = $event->getSimpleSelector();
        $this->doApplyAttributeSelector($attrSelector, $event->getQuery());
    }
    
    protected function doApplyAttributeSelector(SimpleSelector\Attribute $attrSelector, $query)
    {
        $operator   = $attrSelector->getOperator();
        $attr       = $attrSelector->getAttribute();
        $cond       = $attrSelector->getCondition();
        $element    = $this->getElement();
        
        /**
         * Map attribute name
         */
        $attr = $this->mapAttribute($attr);
        
        // Field expression
        $field = $query->field($attr);
        
        // Convert based on field type
        $meta = $this->getElementMetadata($element);
        
        $fields = explode('.', $attr);
        foreach ($fields as $index => $fieldName) {
            if ($meta->hasAssociation($fieldName)) {
                $fieldMapping = $meta->getFieldMapping($fieldName);
                $meta = $this->getDocumentManager()->getClassMetadata(
                    $fieldMapping['targetDocument']);
            } elseif ($index === (sizeof($fields) - 1)) {
                $type = $meta->getTypeOfField($attr);
                
                if (! $type) {
                    foreach ($meta->parentClasses as $class) {
                        $type = $this->getDocumentManager()
                            ->getClassMetadata($class)
                            ->getTypeOfField($attr);
                        if ($type)
                            break;
                    }
                }
                
                if ($type && $type !== 'collection' && $type !== 'one') {
                    $cond = Type::getType($type)->convertToDatabaseValue($cond);
                }
            } else {
                throw new Exception\UnknownFieldException(
                    sprintf("Unknown field '%s'", $attr));
            }
        }
        
        switch ($operator) {
            case null:
                $field->exists(true);
                break;
            case SimpleSelector\Attribute::OPERATOR_EQUALS:
                $field->equals($cond);
                break;
            case SimpleSelector\Attribute::OPERATOR_NOT_EQUALS:
                $field->notEqual($cond);
                break;
            case SimpleSelector\Attribute::OPERATOR_GREATER_THAN:
                $field->gt($cond);
                break;
            case SimpleSelector\Attribute::OPERATOR_GREATER_THAN_OR_EQUAL:
                $field->gte($cond);
                break;
            case SimpleSelector\Attribute::OPERATOR_LESS_THAN:
                $field->lt($cond);
                break;
            case SimpleSelector\Attribute::OPERATOR_LESS_THAN_OR_EQUAL:
                $field->lte($cond);
                break;
            case SimpleSelector\Attribute::OPERATOR_IN_LIST:
                
                $list = explode(' ', $cond);
                array_map('trim', $list);
                
                $field->in($list);
                break;
            case SimpleSelector\Attribute::OPERATOR_REG_EXP:
                $field->operator('$regex', $cond);
                break;
            case SimpleSelector\Attribute::OPERATOR_SUBSTR_MATCH:
                $field->operator('$regex', '.*' . preg_quote($cond, '/') . '.*');
                break;
            case SimpleSelector\Attribute::OPERATOR_SUBSTR_PREFIX:
                $field->operator('$regex', '^' . preg_quote($cond, '/') . '.*');
                break;
            case SimpleSelector\Attribute::OPERATOR_SUBSTR_SUFFIX:
                $field->operator('$regex', '' . preg_quote($cond, '/') . '$');
                break;
            default:
                throw new Exception\UnknownOperatorException(
                        sprintf("Unknown operator '%s'", $operator));
                break;
        }
        
        return true;
    }

    public function applyPseudoSelector(SimpleSelectorEvent $event)
    {
        $pseudoSelector = $event->getSimpleSelector();
        
        if ($pseudoSelector instanceof SimpleSelector\Pseudo\Sort) {
            
            $this->createQueryBuilder()->sort(
                $pseudoSelector->getAttribute(), 
                $pseudoSelector->getOrder());
            
            return true;
        } elseif ($pseudoSelector->getClassName() == 'limit') {
            $this->createQueryBuilder()->limit(
                intval($pseudoSelector->getClassValue()));
            return false;
        } elseif (in_array($pseudoSelector->getClassName(), 
                [
                    'startingFrom',
                    'offset'
                ])) {
            $this->createQueryBuilder()->skip(
                intval($pseudoSelector->getClassValue()));
            return false;
        } else {
            return false;
        }
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
     * Retrieve current element name
     *
     * @return array
     */
    protected function getElement()
    {
        return $this->element;
    }

    /**
     * Retrieve current document name
     *
     * @return string
     */
    protected function getDocument()
    {
        if ($this->getElement()) {
            $map = $this->getDocumentNames();
            return $map[$this->getElement()];
        } else {
            return null;
        }
    }

    /**
     * Retrieve current element name and throw
     * exception if not found
     *
     * @throws \Exception
     * @return string
     */
    protected function requireElement()
    {
        $element = $this->getElement();
        
        if (! $element) {
            throw new \Exception("Sequence doesn't contain element information");
        }
        
        return $element;
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
}