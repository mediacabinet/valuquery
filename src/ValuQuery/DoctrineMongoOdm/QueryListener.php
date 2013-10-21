<?php
namespace ValuQuery\DoctrineMongoOdm;

use ValuQuery\QueryBuilder\Event\SimpleSelectorEvent;
use ValuQuery\QueryBuilder\Event\SequenceEvent;
use ValuQuery\QueryBuilder\Event\QueryBuilderEvent;
use ValuQuery\MongoDb\QueryListener as BaseListener;
use Doctrine\ODM\MongoDB\Types\Type;
use Doctrine\ODM\MongoDB\DocumentManager;
use ArrayAccess;

class QueryListener extends BaseListener
{
    const QUERY_PARAM_NS = '__doctrine_mongodb_odm';
    
    /**
     * @var DocumentManager
     */
    protected $documentManager;
    
    /**
     * Discriminator map
     * 
     * @var array
     */
    protected $discriminatorMap;
    
    public function __construct(DocumentManager $dm)
    {
        $this->setDocumentManager($dm);
    }
    
    public function attach(EventManagerInterface $events)
    {
        parent::attach($events);
        
        $this->listeners[] = $events->attach(
            'finalizeQuery',
            array(
                $this,
                'finalizeQuery'
            ));
    }
    
    public function finalizeQuery(QueryBuilderEvent $event)
    {
        // Remove any custom parameters from the query
        $query = $event->getQuery();
        unset($query[self::QUERY_PARAM_NS]);
    }
    
    public function applyElementSelector(SimpleSelectorEvent $event)
    {
        $elementSelector = $event->getSimpleSelector();
        $class = $this->getElementMetadata($elementSelector->getValue());
        
        if ($class) {
            $discrField = $class->discriminatorField['name'];
            $discrValue = $class->discriminatorValue;
            
            $query = $event->getQuery();
            $this->applyQueryCommand($query, $discrField, null, $discrValue);
            
            $this->setQueryParam(
                $query,
                'documentName',
                $this->getDocumentNameForElement($elementSelector->getValue())
            );
            
            return true;
        } else {
            return false;
        }
    }
    
    public function applyPathSelector(SimpleSelectorEvent $event)
    {
        $pathSelector = $event->getSimpleSelector();
        $query = $event->getQuery();
        
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
            
            $pattern = '^' . SimpleSelector\Path::PATH_SEPARATOR .
                         implode(SimpleSelector\Path::PATH_SEPARATOR, $path) . 
                         '$';
          
            $this->applyQueryCommand(
                $query, 
                $this->getPathField(), 
                'regex', 
                $pattern);
        } else {
            $this->applyQueryCommand(
                $query, 
                $this->getPathField(), 
                null, 
                SimpleSelector\Path::PATH_SEPARATOR);
        }
        
        return true;
    }
    
    /**
     * Retrieve current document manager instance
     *
     * @return DocumentManager
     */
    public function getDocumentManager()
    {
        return $this->documentManager;
    }
    
    public function setDocumentManager(DocumentManager $dm)
    {
        $this->documentManager = $dm;
    }

    /**
     * @return multitype:
     */
    public function getDiscriminatorMap()
    {
        return $this->discriminatorMap;
    }

	/**
     * @param multitype: $discriminatorMap
     */
    public function setDiscriminatorMap($discriminatorMap)
    {
        $this->discriminatorMap = $discriminatorMap;
    }
    
    /**
     * @see \ValuQuery\MongoDb\QueryListener::filterField()
     */
    protected function filterField(ArrayAccess $query, $field, $value)
    {
        $documentName = $this->getQueryParam(
            $query, 
            'documentName', 
            $this->getDefaultDocumentName());
        
        if ($documentName) {
            
            $meta   = $this->getDocumentManager()->getClassMetadata($documentName);
            $fields = explode('.', $field);
            
            foreach ($fields as $index => $fieldName) {
                if ($meta->hasAssociation($fieldName)) {
                    $fieldMapping = $meta->getFieldMapping($fieldName);
                    $meta =  $this->getDocumentManager()->getClassMetadata($fieldMapping['targetDocument']);
                } elseif($index === (sizeof($fields)-1)) {
                    $type = $meta->getTypeOfField($fieldName);
                    
                    if (!$type) {
                        foreach ($meta->parentClasses as $class)
                        {
                            $type = $this->getDocumentManager()->getClassMetadata($class)->getTypeOfField($fieldName);
                            if($type) break;
                        }
                    }
                    
                    if ($type && $type !== 'collection' && $type !== 'one') {
                        $value = Type::getType($type)->convertToDatabaseValue($value);
                    }
                } else {
                    throw new Exception\UnknownFieldException(
                        sprintf("Unknown field '%s'", $field));
                }
            }
        }
        
        return $value;
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
        return array_values($this->getDiscriminatorMap());
    }
    
    /**
     * Retrieve document name for element name
     * 
     * @param string $element
     * @return string|null
     */
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
            
            $field = $this->getPathField();
            
            // Select path attribute
            $qb->select($field)->hydrate(false);
            
            // Use template to create a new selector query
            $template = $this->getSequence()->getSelectorTemplate();
            $selector = $template->createSelector($items[0]);
            $selector->setDocumentNames([
                Sequence::DEFAULT_ELEMENT => $documentName
            ]);
            $selector->extendQuery($qb);
            
            // Fetch data
            $result = $qb->limit(1)
                ->getQuery()
                ->getSingleResult();
            
            // Exit early if selector could not be resolved
            if (! $result || ! isset($result[$field]) || ! $result[$field]) {
                return false;
            }
            
            $path = ltrim($result[$field], '/');
            
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
     * Set custom query parameter
     * 
     * @param ArrayAccess $query
     * @param string $param
     * @param mixed $value
     */
    private function setQueryParam($query, $param, $value)
    {
        $query[self::QUERY_PARAM_NS][$param] = $value;
    }
    
    /**
     * Retrieve custom query parameter
     * +
     * @param ArrayAccess $query
     * @param string $param
     * @param mixed $default
     * @return string
     */
    private function getQueryParam(ArrayAccess $query, $param, $default = null)
    {
        if (isset($query[self::QUERY_PARAM_NS]) 
            && array_key_exists($param, $query[self::QUERY_PARAM_NS])) {
            return $query[self::QUERY_PARAM_NS][$param];
        } else {
            return $default;
        }
    }
}