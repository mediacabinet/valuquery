<?php
namespace ValuQuery\DoctrineMongoOdm\Listener;

use ValuQuery\DoctrineMongoOdm\Exception;
use ValuQuery\DoctrineMongoOdm\ValueConverter;
use ValuQuery\DoctrineMongoOdm\Path\ResolverInterface;
use ValuQuery\QueryBuilder\Event\SimpleSelectorEvent;
use ValuQuery\QueryBuilder\Event\QueryBuilderEvent;
use ValuQuery\MongoDb\QueryListener as BaseListener;
use ValuQuery\Selector\SimpleSelector;
use Doctrine\ODM\MongoDB\DocumentManager;
use Zend\EventManager\EventManagerInterface;
use ValuQuery\Selector\SimpleSelector\Path;
use ArrayAccess;
use ValuQuery\DoctrineMongoOdm\Path\Resolver;

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
    
    /**
     * ResolverInterface
     * 
     * @var ResolverInterface
     */
    protected $pathResolver;
    
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
            
            $path = $this->resolvePath($pathSelector);
            
            // Apply expression that causes this query to return null
            if (!$path) {
                $selector = new SimpleSelector\Attribute(
                    '_id', 
                    SimpleSelector\Attribute::OPERATOR_EQUALS, 
                    false);
                
                return $this->doApplyAttributeSelector($selector, $event->getQuery());
            }
            
            if (substr($path, 0, 1) === "^") {
                $this->applyQueryCommand(
                        $query,
                        $this->getPathField(),
                        'regex',
                        $path);
            } else {
                $this->applyQueryCommand(
                        $query,
                        $this->getPathField(),
                        null,
                        $path);
            }
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

    /**
     * Retrieve default document name
     * 
     * @return string
     */
    public function getDefaultDocumentName()
    {
        return $this->defaultDocumentName;
    }

    /**
     * Set default document name
     * 
     * @param string $defaultDocumentName
     */
    public function setDefaultDocumentName($defaultDocumentName)
    {
        $this->defaultDocumentName = $defaultDocumentName;
    }
    
    /**
     * Set path resolver
     * 
     * @param ResolverInterface $pathResolver
     */
    public function setPathResolver(ResolverInterface $pathResolver)
    {
        $this->pathResolver = $pathResolver;
    }
    
    /**
     * Retrieve path resolver
     * 
     * @return ResolverInterface
     */
    public function getPathResolver()
    {
        if (!$this->pathResolver) {
            $this->pathResolver = new Resolver($this->getDocumentManager(), $this->getDefaultDocumentName());
            $this->pathResolver->setValueConverter($this->getValueConverter());
            $this->pathResolver->setRoleField($this->getRoleField());
            $this->pathResolver->setPathField($this->getPathField());
        }
        
        return $this->pathResolver;
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
     * @param Path $path
     * @return string
     * @throws \Exception
     */
    protected function resolvePath(Path $path)
    {
        $resolver     = $this->getPathResolver();
        $resolvedPath = $resolver->resolve($path);
        
        if ($resolvedPath && strstr($resolvedPath, '*') !== false) {
            
            $resolvedPath = ltrim($resolvedPath, Path::PATH_SEPARATOR);
            $items = explode(Path::PATH_SEPARATOR, $resolvedPath);
            
            // Convert string items to reg exp
            foreach ($items as &$value) {
                if (is_string($value)) {
                    $value = preg_quote($value, Path::PATH_SEPARATOR);
                    $value = str_replace('\*', '.*', $value);
                }
            }
            
            $pattern = Path::PATH_SEPARATOR . implode(Path::PATH_SEPARATOR, $items);
            return '^' . $pattern . '$';
        } else {
            return $resolvedPath;
        }
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