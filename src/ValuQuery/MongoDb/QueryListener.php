<?php
namespace ValuQuery\MongoDb;

use ValuQuery\MongoDb\Exception;
use ValuQuery\QueryBuilder\Event\SimpleSelectorEvent;
use ValuQuery\QueryBuilder\Event\QueryBuilderEvent;
use ValuQuery\Selector\SimpleSelector;
use ValuQuery\Selector\SimpleSelector\Path;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerInterface;
use ArrayObject;
use ArrayAccess;

class QueryListener implements ListenerAggregateInterface
{
    /**
     *
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = array();
    
    /**
     * Mongo command prefix
     *
     * @var string
     */
    private $cmd = '$';
    
    /**
     * Name of the field to match role selector
     * 
     * @var string
     */
    private $roleField = 'roles';
    
    /**
     * Name of the field to match class selector
     *
     * @var string
     */
    private $classField = 'classes';
    
    /**
     * Name of the field to match path selector
     *
     * @var string
     */
    private $pathField = 'path';

    /**
     *
     * @see \Zend\EventManager\ListenerAggregateInterface::attach()
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(
            'prepareQuery', 
            array(
                $this,
                'prepareQuery'
            ));
        
        $this->listeners[] = $events->attach(
            'combineSequence', 
            array(
                $this,
                'combineSequence'
            ));
        $this->listeners[] = $events->attach(
            'applyUniversalSelector',
            array(
                $this,
                'applyUniversalSelector'
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

    public function prepareQuery(QueryBuilderEvent $event)
    {
        if (!$event->getQuery()) {
            $event->setQuery(
                new ArrayObject()
            );
        }
        
        $query = $event->getQuery();
        if (!isset($query['query'])) {
            $query['query'] = [];
        }
    }

    public function combineSequence(EventInterface $event)
    {}
    
    public function applyUniversalSelector()
    {
        return true;
    }

    public function applyElementSelector(SimpleSelectorEvent $event)
    {}

    public function applyIdSelector(SimpleSelectorEvent $event)
    {
        $idSelector = $event->getSimpleSelector();
        $this->applyQueryCommand(
            $event->getQuery(), 
            '_id', 
            null, 
            $idSelector->getCondition());
        
        return true;
    }

    public function applyRoleSelector(SimpleSelectorEvent $event)
    {
        if ($this->getRoleField()) {
            $roleSelector = $event->getSimpleSelector();
            
            $this->applyQueryCommand(
                $event->getQuery(),
                $this->getRoleField(),
                'in',
                [$roleSelector->getCondition()],
                true);
            
            return true;
        }
    }

    public function applyClassSelector(SimpleSelectorEvent $event)
    {
        if ($this->getClassField()) {
            $classSelector = $event->getSimpleSelector();
            
            $this->applyQueryCommand(
                $event->getQuery(), 
                $this->getClassField(), 
                'in', 
                [$classSelector->getCondition()], 
                true);
            
            return true;
        }
    }
    
    public function applyPathSelector(SimpleSelectorEvent $event)
    {
        if ($this->getPathField()) {
            $pathSelector = $event->getSimpleSelector();
            
            $this->applyQueryCommand(
                $event->getQuery(), 
                $this->getPathField(), 
                null, 
                Path::PATH_SEPARATOR . ltrim($pathSelector->getPath(), Path::PATH_SEPARATOR));
            
            return true;
        }
    }

    public function applyAttributeSelector(SimpleSelectorEvent $event)
    {
        $attrSelector = $event->getSimpleSelector();
        return $this->doApplyAttributeSelector($attrSelector, $event->getQuery());
    }
    
    public function applyPseudoSelector(SimpleSelectorEvent $event)
    {
        $pseudoSelector = $event->getSimpleSelector();
        $query = $event->getQuery();
        
        if ($pseudoSelector instanceof SimpleSelector\Pseudo\Sort) {
            $order = strtolower($pseudoSelector->getOrder()) === 'asc' ? 1 : -1;
            $query['sort'][$pseudoSelector->getAttribute()] = $order;
            return true;
        } elseif ($pseudoSelector->getClassName() == 'limit') {
            $query['limit'] = intval($pseudoSelector->getClassValue()); 
            return true;
        } elseif (in_array($pseudoSelector->getClassName(), 
                [
                    'startingFrom',
                    'offset',
                    'skip'
                ])) {
            
            $query['skip'] = intval($pseudoSelector->getClassValue());
            return true;
        }
    }

    /**
     * @return string
     */
    public function getRoleField()
    {
        return $this->roleField;
    }

	/**
     * @param string $roleField
     */
    public function setRoleField($roleField)
    {
        $this->roleField = $roleField;
    }

	/**
     * @return string
     */
    public function getClassField()
    {
        return $this->classField;
    }

	/**
     * @param string $classField
     */
    public function setClassField($classField)
    {
        $this->classField = $classField;
    }

	/**
     * @return string
     */
    public function getPathField()
    {
        return $this->pathField;
    }

	/**
     * @param string $pathField
     */
    public function setPathField($pathField)
    {
        $this->pathField = $pathField;
    }
    
    protected function doApplyAttributeSelector(SimpleSelector\Attribute $attrSelector, $query)
    {
        $operator   = $attrSelector->getOperator();
        $attr       = $attrSelector->getAttribute();
        $cond       = $attrSelector->getCondition();
        
        switch ($operator) {
            case null:
                $this->applyQueryCommand($query, $attr, 'exists', true);
                break;
            case SimpleSelector\Attribute::OPERATOR_EQUALS:
                $this->applyQueryCommand($query, $attr, null, $cond);
                break;
            case SimpleSelector\Attribute::OPERATOR_NOT_EQUALS:
                $this->applyQueryCommand($query, $attr, 'ne', $cond);
                break;
            case SimpleSelector\Attribute::OPERATOR_GREATER_THAN:
                $this->applyQueryCommand($query, $attr, 'gt', $cond);
                break;
            case SimpleSelector\Attribute::OPERATOR_GREATER_THAN_OR_EQUAL:
                $this->applyQueryCommand($query, $attr, 'gte', $cond);
                break;
            case SimpleSelector\Attribute::OPERATOR_LESS_THAN:
                $this->applyQueryCommand($query, $attr, 'lt', $cond);
                break;
            case SimpleSelector\Attribute::OPERATOR_LESS_THAN_OR_EQUAL:
                $this->applyQueryCommand($query, $attr, 'lte', $cond);
                break;
            case SimpleSelector\Attribute::OPERATOR_IN_LIST:
                
                if (is_string($cond)) {
                    $list = preg_split('/\s+/', trim($cond));
                    array_map('trim', $list);
                } else if (!is_array($cond)) {
                    $list = [$cond];
                } else {
                    $list = $cond;
                }
                
                $this->applyQueryCommand($query, $attr, 'in', $list);
                break;
            case SimpleSelector\Attribute::OPERATOR_REG_EXP:
                $this->applyRegEx($query, $attr, $cond);
                break;
            case SimpleSelector\Attribute::OPERATOR_SUBSTR_MATCH:
                $this->applyQueryCommand($query, $attr, 'regex', '.*' . preg_quote($cond, '/') . '.*');
                break;
            case SimpleSelector\Attribute::OPERATOR_SUBSTR_PREFIX:
                $this->applyQueryCommand($query, $attr, 'regex', '^' . preg_quote($cond, '/'));
                break;
            case SimpleSelector\Attribute::OPERATOR_SUBSTR_SUFFIX:
                $this->applyQueryCommand($query, $attr, 'regex', '' . preg_quote($cond, '/') . '$');
                break;
            default:
                return new Exception\UnknownOperatorException(
                    sprintf("Unknown operator '%s'", $operator));
                break;
        }
        
        return true;
    }
    
    /**
     * Apply regex command
     * 
     * @param ArrayAccess $query
     * @param string $field
     * @param string $pattern
     */
    protected function applyRegEx(ArrayAccess $query, $field, $pattern)
    {
        // Parse inline regex modifiers (i, m, x or s)
        if (preg_match('/^\(\?([imxs]+)\)(.*)/', $pattern, $matches)) {
            $this->applyQueryCommand($query, $field, 'regex', $matches[2]);
            $this->applyQueryCommand($query, $field, 'options', $matches[1]);
        } else {
            $this->applyQueryCommand($query, $field, 'regex', $pattern);
        }
    }
    
    /**
     * Apply query command
     * 
     * @param ArrayAccess $query    Query array
     * @param string $field         Field name
     * @param string|null $command  Command name (leave null for 'is equal to' command)
     * @param mixed $value          Query value (condition)
     * @param boolean $append       Set true to append value to existing value array
     *                              if it has been previously set
     */
    protected function applyQueryCommand(ArrayAccess $query, $field, $command, $value, $append = false)
    {
        $this->filterField($query, $field, $value);
        
        if ($command) {
            $cmd = $this->cmd.$command;
            
            if ($append
                && isset($query['query'][$field][$cmd])
                && is_array($query['query'][$field][$cmd])) {
                $query['query'][$field][$cmd] = array_merge(
                        $query['query'][$field][$cmd], (array)$value);
            } else {
                $query['query'][$field][$cmd] = $value;
            }
        } else {
            $query['query'][$field] = $value;
        }
    }
    
    /**
     * Filter value of field for query
     * 
     * @param ArrayAccess $query
     * @param string $field     Field name
     * @param mixed $value      Value to filter
     */
    protected function filterField(ArrayAccess $query, &$field, &$value)
    {}
}