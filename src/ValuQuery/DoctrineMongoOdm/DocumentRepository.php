<?php
namespace ValuQuery\DoctrineMongoOdm;

use Doctrine\ODM\MongoDB\DocumentRepository as BaseRepository;

class DocumentRepository extends BaseRepository
{
    
    /**
     * Query helper
     * 
     * @var QueryHelper
     */
    protected $queryHelper = null;
    
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
     * Retrieve query helper
     * 
     * @return \ValuQuery\DoctrineMongoOdm\QueryHelper
     */
    public function getQueryHelper()
    {
        if ($this->queryHelper === null) {
            $this->queryHelper = new QueryHelper($this->getDocumentManager(), $this->getClassName());
            
            $meta = $this->getClassMetadata();
            $idMeta = $meta->getFieldMapping($meta->getIdentifier());
            
            if ($idMeta) {
                if (isset($idMeta['strategy']) && strtoupper($idMeta['strategy']) === 'UUID') {
                    $this->queryHelper->enableIdDetection(QueryHelper::ID_UUID5);   
                } elseif (!isset($idMeta['strategy']) || strtoupper($idMeta['strategy']) === 'AUTO') {
                    $this->queryHelper->enableIdDetection(QueryHelper::ID_MONGO);
                }
            }
        }
        
        return $this->queryHelper;
    }
}