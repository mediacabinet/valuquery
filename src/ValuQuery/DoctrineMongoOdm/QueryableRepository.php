<?php
namespace ValuQuery\DoctrineMongoOdm;

use Doctrine\ODM\MongoDB\DocumentRepository as BaseRepository;

class QueryableRepository extends BaseRepository
{
    
    /**
     * Query helper
     * 
     * @var QueryHelper
     */
    protected $queryHelper = null;
    
    /**
     * Name of the field to match role selector
     *
     * @var string
     */
    protected $roleField = null;
    
    /**
     * Name of the field to match class selector
     *
     * @var string
     */
    protected $classField = null;
    
    /**
     * Name of the field to match path selector
     *
     * @var string
     */
    protected $pathField = null;
    
    /**
     * @see \ValuQuery\DoctrineMongoOdm\QueryHelper::query()
     */
    public function query($query, $fields = null)
    {
        return $this->getQueryHelper()->query($query, $fields);
    }
    
    /**
     * @see \ValuQuery\DoctrineMongoOdm\QueryHelper::queryOne()
     */
    public function queryOne($query, $fields = null)
    {
        return $this->getQueryHelper()->queryOne($query, $fields);
    }
    
    /**
     * @see \ValuQuery\DoctrineMongoOdm\QueryHelper::count()
     */
    public function count($query)
    {
        return $this->getQueryHelper()->count($query);
    }
    
    /**
     * @see \ValuQuery\DoctrineMongoOdm\QueryHelper::exists()
     */
    public function exists($query)
    {
        return $this->getQueryHelper()->exists($query);
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
    
    /**
     * Retrieve query helper
     * 
     * @return \ValuQuery\DoctrineMongoOdm\QueryHelper
     */
    public function getQueryHelper()
    {
        if ($this->queryHelper === null) {
            $this->queryHelper = new QueryHelper($this->getDocumentManager(), $this->getClassName());
            
            $idStrategy = $this->getIdentifierStrategy();
            
            if ($idStrategy) {
                if ($idStrategy === 'uuid') {
                    $this->queryHelper->enableIdDetection(QueryHelper::ID_UUID5);   
                } elseif ($idStrategy === 'auto') {
                    $this->queryHelper->enableIdDetection(QueryHelper::ID_MONGO);
                }
            }
            
            if (($pathField = $this->getPathField()) != false) {
                $this->queryHelper
                     ->getDefaultQueryListener()
                     ->setPathField($pathField);
            }
            
            if (($classField = $this->getClassField()) != false) {
                $this->queryHelper
                     ->getDefaultQueryListener()
                     ->setClassField($classField);
            }
            
            if (($roleField = $this->getRoleField()) != false) {
                $this->queryHelper
                     ->getDefaultQueryListener()
                     ->setRoleField($roleField);
            }
        }
        
        return $this->queryHelper;
    }
    
    /**
     * Retrieve identifier strategy
     * 
     * @return string Strategy as undercase string (e.g. "auto" or "uuid")
     */
    private function getIdentifierStrategy()
    {
        $meta       = $this->getClassMetadata();
        $identifier = $meta->getIdentifier();
        $field      = array_pop($identifier);
        
        if ($field) {
            $mapping = $meta->getFieldMapping($field);
            return isset($mapping['strategy']) ? strtolower($mapping['strategy']) : null; 
        } else {
            return null;
        }
    }
}