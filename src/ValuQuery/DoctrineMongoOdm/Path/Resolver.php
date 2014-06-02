<?php
namespace ValuQuery\DoctrineMongoOdm\Path;

use ValuQuery\DoctrineMongoOdm\Path\Exception\IllegalPathSelectorException;
use ValuQuery\Selector\SimpleSelector\Path;
use ValuQuery\Selector\SimpleSelector\Id;
use ValuQuery\Selector\SimpleSelector\Role;
use ValuQuery\Selector\SimpleSelector\SimpleSelectorInterface;
use ValuQuery\DoctrineMongoOdm\ValueConverter;
use Doctrine\ODM\MongoDB\DocumentManager;

class Resolver
    implements ResolverInterface
{
    
    /**
     * @var DocumentManager
     */
    protected $documentManager;
    
    /**
     * ValueConverter
     *
     * @var ValueConverter
     */
    protected $valueConverter;
    
    /**
     * Document name
     *
     * @var string
     */
    protected $documentName;
    
    /**
     * Name of the field to match role selector
     *
     * @var string
     */
    private $roleField = 'roles';
    
    /**
     * Name of the field to match path selector
     *
     * @var string
     */
    private $pathField = 'path';
    
    public function __construct(DocumentManager $dm, $documentName = null)
    {
        $this->setDocumentManager($dm);
        
        if ($documentName) {
            $this->setDocumentName($documentName);
        }
    }
    
    /**
     * @see \ValuQuery\DoctrineMongoOdm\Path\ResolverInterface::resolve()
     */
    public function resolve(Path $path)
    {
        $items = $path->getPathItems();
        $documentName = $this->getDocumentName();
        
        if (! sizeof($items)) {
            return false;
        } elseif ($items[0] instanceof SimpleSelectorInterface) {
        
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
                throw new IllegalPathSelectorException(
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
                    explode(Path::PATH_SEPARATOR, $path),
                    array_values($items));
        }
        
        // Convert string items to reg exp
        foreach ($items as &$value) {
            if (is_string($value)) {
                $value = preg_quote($value, '/');
                $value = str_replace('\*', '.*', $value);
            }
        }
        
        return Path::PATH_SEPARATOR . implode(Path::PATH_SEPARATOR, $items);
    }
    
    /**
     * Set document manager
     * 
     * @param DocumentManager $documentManager
     */
    public function setDocumentManager(DocumentManager $documentManager)
    {
        $this->documentManager = $documentManager;
    }
    
    /**
     * Retrieve document manager
     * 
     * @return DocumentManager
     */
    public function getDocumentManager()
    {
        return $this->documentManager;
    }
    
    /**
     * Set value converter instance
     * 
     * @param ValueConverter $converter
     */
    public function setValueConverter(ValueConverter $converter)
    {
        $this->valueConverter = $converter;
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
     * Set document name for resolving paths
     * 
     * @param string $documentName
     */
    public function setDocumentName($documentName)
    {
        $this->documentName = $documentName;
    }
    
    /**
     * Retrieve document name for resolving paths
     * 
     * @return string
     */
    public function getDocumentName()
    {
        return $this->documentName;
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
}