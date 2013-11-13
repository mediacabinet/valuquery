<?php
namespace ValuQuery\DoctrineMongoOdm\Listener;

use ValuQuery\DoctrineMongoOdm\Exception;
use ValuQuery\DoctrineMongoOdm\ValueConverter;
use ValuQuery\QueryBuilder\Event\SimpleSelectorEvent;
use ValuQuery\QueryBuilder\Event\QueryBuilderEvent;
use ValuQuery\MongoDb\QueryListener as BaseListener;
use ValuQuery\Selector\SimpleSelector;
use Doctrine\ODM\MongoDB\DocumentManager;
use Zend\EventManager\EventManagerInterface;
use ValuQuery\Selector\SimpleSelector\Id;
use ValuQuery\Selector\SimpleSelector\Role;
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
    
    /**
     * Default document name
     * 
     * @var string
     */
    protected $defaultDocumentName;
    
    /**
     * ValueConverter
     * 
     * @var ValueConverter
     */
    protected $valueConverter;
    
    public function __construct(DocumentManager $dm, $documentName = null)
    {
        $this->setDocumentManager($dm);
        
        if ($documentName) {
            $this->setDefaultDocumentName($documentName);
            $map = $dm->getClassMetadata($documentName)->discriminatorMap;
            
            if (is_array($map)) {
                $this->setDiscriminatorMap($map);
            }
        }
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
        
        if (isset($query[self::QUERY_PARAM_NS])) {
            unset($query[self::QUERY_PARAM_NS]);
        }
    }
    
    public function applyElementSelector(SimpleSelectorEvent $event)
    {
        $elementSelector = $event->getSimpleSelector();
        $documentName = $this->getDocumentNameForElement($elementSelector->getElement());
        
        if (!$documentName) {
            return new Exception\UnknownElementException(
                sprintf('Unknown element "%s" in query', $elementSelector->getElement())
            );
        }
        
        $class = $this->getElementMetadata($elementSelector->getElement());
        $query = $event->getQuery();
        
        $this->setQueryParam(
            $query,
            'documentName',
            $documentName
        );
        
        $discrField = $class->discriminatorField['name'];
        $discrValue = $class->discriminatorValue;
        
        $this->applyQueryCommand($query, $discrField, null, $discrValue);
        
        return true;
    }
    
    public function applyPathSelector(SimpleSelectorEvent $event)
    {
        $pathSelector = $event->getSimpleSelector();
        $query = $event->getQuery();
        
        if (sizeof($pathSelector->getPathItems())) {
            
            $documentName = $this->getQueryParam(
                    $query,
                    'documentName',
                    $this->getDefaultDocumentName());
            
            $path = $this->translatePathArray($documentName, $pathSelector->getPathItems());
            
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
     *
     * @param multitype: $discriminatorMap            
     */
    public function setDiscriminatorMap($discriminatorMap)
    {
        $this->discriminatorMap = $discriminatorMap;
    }

    public function getDefaultDocumentName()
    {
        return $this->defaultDocumentName;
    }

    public function setDefaultDocumentName($defaultDocumentName)
    {
        $this->defaultDocumentName = $defaultDocumentName;
    }
    
    /**
     * Retrieve value converter
     * 
     * @return \ValuQuery\DoctrineMongoOdm\ValueConverter
     */
    protected function getValueConverter()
    {
        if ($this->valueConverter === null) {
            $this->valueConverter = new ValueConverter($this->getDocumentManager());
        }
        
        return $this->valueConverter;
    }
     
    /**
     * @see \ValuQuery\MongoDb\QueryListener::filterField()
     */
    protected function filterField(ArrayAccess $query, &$field, &$value)
    {
        $documentName = $this->getQueryParam(
            $query, 
            'documentName', 
            $this->getDefaultDocumentName());
        
        if ($documentName) {
            $this->filterFieldForDocument($documentName, $field, $value);
        }
    }
    
    /**
     * Retrieve document name for element name
     * 
     * @param string $element
     * @return string|null
     */
    protected function getDocumentNameForElement($element)
    {
        $map = $this->getDiscriminatorMap();
        return isset($map[$element]) ? $map[$element] : null;
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
     * @param string $documentName
     * @param array $items
     *            Path items
     * @return array
     * @throws \Exception
     */
    protected function translatePathArray($documentName, array $items)
    {
        if (! sizeof($items)) {
            return array();
        } elseif ($items[0] instanceof SimpleSelector\SimpleSelectorInterface) {
            
            $simpleSelector = array_shift($items);
            $pathField = $this->getPathField();
            
            // Create a new Query Builder instance
            $qb = $this->getDocumentManager()->createQueryBuilder($documentName);
            
            // Select path attribute
            $qb->select($pathField)->hydrate(false);

            if ($simpleSelector instanceof Id) {
                $field = '_id';
                $value = $simpleSelector->getCondition();
                $this->filterFieldForDocument($documentName, $field, $value);
                
                $qb->field($field)->equals($value);
            } else if ($simpleSelector instanceof Role) {
                $qb->field($this->getRoleField())->in([
                    $simpleSelector->getCondition()]);
            } else {
                throw new Exception\IllegalPathSelectorException(
                    sprintf('Simple selectors of type "%s" are not supported as part of path selector', 
                            $simpleSelector->getName()));
            }
            
            // Fetch data
            $result = $qb->limit(1)
                ->getQuery()
                ->getSingleResult();
            
            // Exit early if selector could not be resolved
            if (! $result || ! isset($result[$pathField]) || ! $result[$pathField]) {
                return false;
            }
            
            $path = ltrim($result[$pathField], '/');
            $items = array_merge(
                    explode(SimpleSelector\Path::PATH_SEPARATOR, $path), 
                    array_values($items));
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
     * Filter field (and/or value) for named document
     * 
     * @param string $documentName
     * @param string $field
     * @param mixed $value
     */
    protected function filterFieldForDocument($documentName, &$field, &$value)
    {
        $this->getValueConverter()->convertToDatabase($documentName, $field, $value);
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