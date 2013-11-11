<?php
namespace ValuQuery\DoctrineMongoOdm;

use ValuQuery\Selector\Parser\SelectorParser;
use ValuQuery\QueryBuilder\QueryBuilder;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\MongoDB\Cursor as BaseCursor;
use Doctrine\ODM\MongoDB\Cursor;
use Doctrine\ODM\MongoDB\LoggableCursor;
use Doctrine\MongoDB\LoggableCursor as BaseLoggableCursor;
use ArrayObject;
use ValuQuery\DoctrineMongoOdm\Exception\InvalidQueryException;

/**
 * DoctineMongoDB ODM query helper
 * 
 */
class QueryHelper
{
    /**
     * Mongo ID type
     * 
     * @var string
     */
    const ID_MONGO = 1;
    
    /**
     * UUID3 type
     * 
     * @var string
     */
    const ID_UUID3 = 2;
    
    /**
     * UUID5 type
     * 
     * @var string
     */
    const ID_UUID5 = 3;
    
    /**
     * Indicates find operation for a single entity
     *
     * @var string
     */
    const FIND_ONE = 1;
    
    /**
     * Indicates find operation for multiple entities
     * 
     * @var string
     */
    const FIND_MANY = 2;

    /**
     * Indicates count operation
     * 
     * @var int
     */
    const COUNT = 3;
    
    /**
     * Indicates query parse operation
     *
     * @var int
     */
    const PARSE = 4;
    
    /**
     * Universal selector
     * 
     * @var string
     */
    const UNIVERSAL_SELECTOR = '*';
    
    /**
     * ID selector prefix
     * 
     * @var string
     */
    const ID_PREFIX = '#';
    
    /**
     * Custom command identifier prefix for array queries
     * 
     * @var string
     */
    const CMD = '@';
    
    /**
     * Document manager
     * 
     * @var DocumentManager
     */
    protected $documentManager;
    
    /**
     * Document name
     * 
     * @var string
     */
    protected $documentName;
    
    /**
     * Length of the ID for autodetection
     * 
     * @var int
     */
    protected $idLength = null;
    
    /**
     * ID type
     * 
     * @var string
     */
    protected $idType = null;
    
    /**
     * QueryBuilder
     * 
     * @var QueryBuilder
     */
    protected $queryBuilder;
    
    /**
     * Default query listener
     * 
     * @var QueryListener
     */
    protected $queryListener;
    
    /**
     * Value converter instance
     * 
     * @var ValueConverter
     */
    protected $converter;
    
    public function __construct(DocumentManager $dm, $documentName)
    {
        $this->documentManager = $dm;
        $this->documentName = $documentName;
    }
    
    /**
     * Perform query and retrieve matched documents or fields
     * 
     * Query may be a string, in which case it is treated as
     * a selector. Query may also be an associative array, 
     * in which case it is passed directly as query criteria to
     * query builder. Last, query may be an array with numeric
     * indexes, in which case it is considered as an array of
     * sub queries (match any).
     * 
     * @param mixed $query                Query
     * @param null|string|array $fields   Field(s) to return
     * @return array|Doctrine\ODM\MongoDB\Cursor Mongo cursor or array of documents or values for requested fields.
     *                                           Returns empty cursor/array if query doesn't match.
     */
    public function query($query, $fields = null)
    {
        return $this->doQuery($query, $fields);
    }
    
    /**
     * Query and retrieve exactly one document or specified document fields
     * 
     * @see Helper::query()        for description of parameter usage
     * 
     * @param string|array $query   Query
     * @param string|array $fields  Field(s) to return
     * @return mixed                Document, field value or array of values. 
     *                              Returns null if query doesn't match.
     */
    public function queryOne($query, $fields = null)
    {
        return $this->doQuery($query, $fields, self::FIND_ONE);
    }
    
    /**
     * Parse query and return corresponding query specs ready to be sent to collection's find method
     * 
     * @param string|array $query
     * @param null|string|array $fields
     * @return array
     */
    public function parse($query, $fields = null)
    {
        return $this->doQuery($query, $fields, self::PARSE);
    }
    
    /**
     * Count number of documents matching the query
     * 
     * @param mixed $query
     * @return int
     */
    public function count($query)
    {
        return $this->doQuery($query, null, self::COUNT);
    }
    
    /**
     * Test whether or not any document matches the query
     * 
     * @param mixed $query
     * @return boolean
     */
    public function exists($query)
    {
        return $this->count($query) >= 1;
    }
    
    /**
     * Test whether given query parameter represents
     * an empty query
     * 
     * @param mixed $query
     * @return boolean
     */
    public function isEmptyQueryParam($query)
    {
        if (is_string($query)) {
            return trim($query) == '';
        } elseif (is_array($query)) {
            return sizeof($query) == 0;
        } elseif($query === null) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Enable ID autodetection
     * 
     * @param string $type ID type
     * @return \Valu\Doctrine\MongoDB\Query\Helper
     */
    public function enableIdDetection($type = self::ID_MONGO)
    {
        switch ($type) {
            case self::ID_MONGO:
                $this->idLength = 24;
                break;
            case self::ID_UUID3:
            case self::ID_UUID5:
                $this->idLength = 32;
                break;
            default:
                throw new \InvalidArgumentException('Unrecognized ID type');
                break;
        }
        
        $this->idType = $type;
        
        return $this;
    }
    
    /**
     * Disable ID autodetection
     * 
     * @return \Valu\Doctrine\MongoDB\Query\Helper
     */
    public function disableIdDetection()
    {
        $this->idLength = null;
        return $this;
    }
    
    /**
     * Retrieve document manager instance
     * 
     * @return DocumentManager
     */
    public function getDocumentManager()
    {
        return $this->documentManager;
    }
    
    /**
     * Retrieve document name
     * 
     * @return string
     */
    public function getDocumentName()
    {
        return $this->documentName;
    }
    
    /**
     * Retrieve query builder instance
     * 
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        if ($this->queryBuilder === null) {
            $this->queryBuilder = new QueryBuilder();
            $this->queryBuilder->getEventManager()->attach($this->getDefaultQueryListener());
        }
        
        return $this->queryBuilder;
    }
    
    /**
     * Retrieve default listener for query builder
     * 
     * @return QueryListener
     */
    public function getDefaultQueryListener()
    {
        if ($this->queryListener === null) {
            $this->queryListener = new QueryListener(
                $this->getDocumentManager(), $this->documentName);
        }
        
        return $this->queryListener;
    }
    
    /**
     * Performs query
     * 
     * @param mixed $query
     * @param array $fields
     * @param int $mode
     * @return multitype:|\Valu\Doctrine\MongoDB\Query\mixed|Ambigous <string, multitype:, \Doctrine\ODM\MongoDB\Cursor, NULL, \Valu\Doctrine\MongoDB\Query\array|\Doctrine\ODM\MongoDB\Cursor, multitype:unknown >|NULL
     */
    private function doQuery($query, $fields = null, $mode = self::FIND_MANY)
    {
        // Return early if query is empty
        if ($this->isEmptyQueryParam($query)) {
            if ($mode === self::FIND_MANY) {
                return array();
            } else {
                return null;
            }
        }
        
        if (is_string($query)) {
            return $this->doFindBySelector($query, $fields, $mode);
        } elseif(is_array($query)) {
            
            if(empty($query) || $this->isAssociativeArray($query)) {
                return $this->doFindByArray($query, $fields, $mode);
            } else {
                $mainQuery = new ArrayObject();
                $mainQuery['query']['$or'] = [];
                
                $this->applyFields($mainQuery, $fields);
                
                foreach ($query as $selector) {
                    $subQuery = new ArrayObject();
                    $this->applySelector($subQuery, $selector, true);
                    
                    $mainQuery['query']['$or'][] = $subQuery['query'];
                    
                    foreach (['sort', 'limit', 'skip'] as $copy) {
                        if (isset($subQuery[$copy]) && !isset($mainQuery[$copy])) {
                            $mainQuery[$copy] = $subQuery[$copy];
                        }
                    }
                }
                
                return $this->process($mainQuery, $fields, $mode);
            }
        } else {
            throw new InvalidQueryException(
                sprintf('Unrecognized query format; string or array expected, %s received', gettype($query))
            );
        }
    }
    
    /**
     * Applies selector to query
     *
     * @param ArrayObject $query
     * @param string $selector
     * @param boolean $useIdDetection
     * @return ArrayObject
     */
    protected function applySelector(ArrayObject $query, $selector, $useIdDetection = false)
    {
        if ($useIdDetection) {
            $id = $this->detectId($selector);
            
            if ($id !== null) {
                $selector = '#'.$selector;
            }
        }
        
        $definition = SelectorParser::parseSelector($selector);
        return $this->getQueryBuilder()->build($definition, $query);
    }
    
    /**
     * Perform find by array of criteria
     * 
     * This functionality is partly copied from DocumentPersister class.
     *
     * @see \Doctrine\ODM\MongoDB\Persisters\DocumentPersister::loadAll()
     * @param array $query
     * @param array|string $fields
     * @param string $mode
     * @return int|null|array|\Doctrine\ODM\MongoDB\Cursor
     */
    protected function doFindByArray(array $query, $fields = null, $mode = self::FIND_MANY)
    {
        $odmQuery = new ArrayObject();
    
        // Parse internal commands
        foreach (array('sort', 'limit', 'offset') as $cmd) {
            if (array_key_exists(self::CMD . $cmd, $query)) {
                
                if ($cmd === 'offset') {
                    $mongoCmd = 'skip';
                } else {
                    $mongoCmd = $cmd;
                }
                
                $odmQuery[$mongoCmd] = $query[self::CMD . $cmd];
                unset($query[self::CMD . $cmd]);
            } else {
                $odmQuery[$cmd] = null;
            }
        }
        
        // Apply fields to odm query
        $this->applyFields($odmQuery, $fields);
        
        // Prepare query (e.g. map PHP attribute names to field names)
        $persister = $this->getUow()->getDocumentPersister($this->documentName);
        $odmQuery['query'] = $persister->prepareQueryOrNewObj($query);

        return $this->process($odmQuery, $fields, $mode);
    }
    
    /**
     * Perform find by selector string
     * 
     * @param string $selector
     * @param string|array $fields
     * @param string $mode
     */
    protected function doFindBySelector($selector, $fields = null, $mode = self::FIND_MANY)
    {
        // Detect ID
        $id = $this->detectId($selector);
        
        // Find documents using faster methods 
        // when ID selector is used
        if ($id !== null && $fields === null && $mode === self::FIND_ONE) {
            return $this->getDocumentManager()
                ->getRepository($this->documentName)
                ->findOneBy(array('id' => $id));
        } elseif ($id !== null) {
            return $this->doFindByArray(array('id' => $id), $fields, $mode);
        }
        
        $query = new ArrayObject();
        $query['query'] = [];
        
        $this->applyFields($query, $fields);
        
        if($selector && ($selector !== self::UNIVERSAL_SELECTOR)){
            $this->applySelector($query, $selector, false);
        }
        
        return $this->process($query, $fields, $mode);    
    }
    
    /**
     * Process query
     * 
     * @param ArrayObject $query
     * @param array|null|string $fields
     * @param int $mode
     * @return boolean|string|\Doctrine\ODM\MongoDB\Cursor|NULL|array
     */
    protected function process(ArrayObject $query, $fields, $mode)
    {
        $preparedQuery = $this->prepareQuery($query, $mode);
        
        if ($mode === self::PARSE) {
            return $preparedQuery;
        } else {
            $result = $this->execute($preparedQuery, $mode);
            return $this->prepareResult($result, $fields, $mode);
        }
    }
    
    /**
     * Prepare query for execute
     * 
     * @param ArrayObject $query
     * @param int $mode
     * @return array
     */
    protected function prepareQuery(ArrayObject $query, $mode)
    {
        $query = $query->getArrayCopy();
        
        $dm = $this->getDocumentManager();
        $classMeta = $dm->getClassMetadata($this->documentName);
        $documentPersister = $this->getUow()->getDocumentPersister($this->documentName);
        
        $query['type'] = \Doctrine\MongoDB\Query\Query::TYPE_FIND;
        
        if ($mode === self::FIND_ONE) {
            $query['type'] = \Doctrine\MongoDB\Query\Query::TYPE_FIND;
            $query['limit'] = 1;
        } else if ($mode === self::COUNT) {
            $query['type'] = \Doctrine\MongoDB\Query\Query::TYPE_COUNT;
        } else {
            $query['type'] = \Doctrine\MongoDB\Query\Query::TYPE_FIND;
        }
        
        // Apply filters
        $query['query'] = $documentPersister->addFilterToPreparedQuery($query['query']);
        
        // Apply discriminator
        $query['query'] = $documentPersister->addDiscriminatorToPreparedQuery($query['query']);
        
        if (!isset($query['select']) || empty($query['select'])) {
            $query['select'] = [];
        } else {
            $query['select'] = $documentPersister->prepareSortOrProjection($query['select']);
        }
        
        if (!isset($query['sort'])) {
            $query['sort'] = [];
        } else {
            $query['sort'] = $documentPersister->prepareSortOrProjection($query['sort']);
        }
        
        if (!array_key_exists('limit', $query)) {
            $query['limit'] = null;
        }
        
        $query['slaveOkay'] = $classMeta->slaveOkay;
        
        return $query;
    }
    
    /**
     * Execute query
     *
     * @param array $query
     * @param int $mode
     * @return \Doctrine\ODM\MongoDB\Cursor|\Doctrine\ODM\MongoDB\LoggableCursor|int
     */
    protected function execute(array $query, $mode)
    {
    
        $dm = $this->getDocumentManager();
        $coll  = $dm->getDocumentCollection($this->documentName);
    
        // Count matched documents or retrieve cursor
        // using find
        if ($mode === self::COUNT) {
            return $coll->count(
                    $query['query'],
                    !isset($query['limit']) ? 0 : $query['limit'],
                    !isset($query['skip']) ? 0 : $query['skip']);
        } else {
            $cursor = $coll->find($query['query'], $query['select']);
            
            if (!isset($query['select']) || empty($query['select'])) {
                $hydrate = true;
            } else {
                $hydrate = false;
            }
  
            // Prepare new cursor
            $newCursor = $this->prepareCursor($cursor, $query);
            
            // No hydration needed, when fields are present
            if (!$hydrate) {
                $newCursor->hydrate(false);
            }
    
            // Fetch a single result or cursor
            if ($mode == self::FIND_ONE) {
                $result = $newCursor->getSingleResult();
            } else {
                $result = $newCursor;
            }
    
            return $result;
        }
    }
    
    /**
     * Prepares query result
     *
     * @param array|\Doctrine\ODM\MongoDB\Cursor $result
     * @param null|string|array $fields
     * @param int $mode
     * @return string|array|\Doctrine\ODM\MongoDB\Cursor|null
     */
    protected function prepareResult($result, $fields, $mode)
    {
        if ($result === null) {
            return null;
        } else if ($mode === self::COUNT && is_int($result)) {
            return $result;
        }
        
        if ($fields === null) {
            return $result;
        }
        
        $convertSingleField = is_string($fields);
        
        if ($mode == self::FIND_ONE) {
            $this->getValueConverter()->convertArrayToPhp(
                $this->getDocumentName(), $result);
            
            if ($convertSingleField) {
                $success = false;
                return $this->findFieldFromArray($fields, $result, $success);
            } else {
                if ((!isset($fields['id']) || $fields['id'] === false) && isset($result['id'])) {
                    unset($result['id']);
                }
                
                return $result;
            }
            
            return $result;
        } else {

            $data = [];
            
            foreach ($result as $documentData) {
                $this->getValueConverter()->convertArrayToPhp(
                        $this->getDocumentName(), $documentData);
                
                if ($convertSingleField) {
                    $success = false;
                    $fieldValue = $this->findFieldFromArray($fields, $documentData, $success);
                    
                    if ($success) {
                        $data[] = $fieldValue;
                    }
                } else {
                    if ((!isset($fields['id']) || $fields['id'] === false) && isset($documentData['id'])) {
                        unset($documentData['id']);
                    }
                    
                    $data[] = $documentData;
                }
            }
            
            return $data;
        }
    }
    
    /**
     * Apply select() for each field
     * 
     * @param ArrayObject $query
     * @param array|string $fields
     */
    protected function applyFields(ArrayObject $query, $fields)
    {
        if (!$fields) {
            return false;
        }
        
        if (is_string($fields)) {
            $query['select'][$fields] = 1;
        } else {
            foreach ($fields as $key => $value) {
                if ($value == true && is_string($key)) {
                    $query['select'][$key] = 1;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Filter array representing single document data
     * 
     * @param array $data
     * @param array|null $fields
     */
    protected function filterDocumentData(&$data, $fields)
    {
        if (isset($data['_id'])) {
        
            if (isset($fields['id']) && $fields['id'] == true) {
                $data['id'] = $data['_id'];
            }
        
            unset($data['_id']);
        }
    }
    
    /**
     * Detect if value represents ID
     * 
     * @param string $value
     * @return string|NULL
     */
    protected function detectId($value)
    {
        $matchLength = false;
        
        if ($this->idLength !== null
            && strlen($value) == $this->idLength) {
            
            $matchLength = true;
        } elseif ($this->idLength !== null
                  && strlen($value) == $this->idLength+1
                  && substr($value, 0, 1) == self::ID_PREFIX) {
            
            $matchLength = true;
            $value = substr($value, 1);
        }
        
        if ($matchLength && ctype_alnum($value)) {
            return $value;
        } else {
            return null;
        }
    }
    
    /**
     * Retrieve value converter instance
     * 
     * @return \ValuQuery\DoctrineMongoOdm\ValueConverter
     */
    protected function getValueConverter()
    {
        if ($this->converter === null) {
            $this->converter = new ValueConverter($this->getDocumentManager());
        }
        
        return $this->converter;
    }
    
    /**
     * Find a field from recursive array
     *
     * @param string $field Field name, may contain dots to mark associations
     * @param array $data
     * @param boolean $success Flag that is set to true, if field was found
     * @return NULL|mixed
     */
    private function findFieldFromArray($field, array $data, &$success)
    {
        $fields = explode('.', $field);
    
        foreach ($fields as $fieldName) {
            if (!is_array($data)) {
                $success = false;
                return null;
            }
    
            if (array_key_exists($fieldName, $data)) {
                $data = $data[$fieldName];
            } else {
                $success = false;
                return null;
            }
        }
    
        $success = true;
        return $data;
    }
    
    /**
     * Retrieve current Unit of Work
     * 
     * @return \Doctrine\ODM\MongoDB\UnitOfWork
     */
    private function getUow()
    {
        return $this->getDocumentManager()->getUnitOfWork();
    }
    
    /**
     * Test whether given array is associative
     * 
     * @param array $array
     * @return boolean    True if associative (and not empty)
     */
    private function isAssociativeArray(array $array)
    {
        return (array_keys($array) !== range(0, count($array) - 1));
    }
    
    /**
     * Wraps the supplied base cursor as an ODM one.
     * 
     * This functionality is copied from DocumentPersister class.
     *
     * @see \Doctrine\ODM\MongoDB\Persisters\DocumentPersister::wrapCursor()
     * @param \Doctrine\MongoDB\Cursor $cursor The base cursor
     *
     * @return Cursor An ODM cursor
     */
    protected function prepareCursor(BaseCursor $cursor, $query)
    {
        // Apply internal commands
        if (isset($query['sort'])) {
            $cursor->sort($query['sort']);
        }
        
        if (isset($query['limit'])) {
            $cursor->limit($query['limit']);
        }
        
        if (isset($query['skip'])) {
            $cursor->skip($query['skip']);
        }
        
        $dm = $this->getDocumentManager();
        $coll = $collection = $dm->getDocumentCollection($this->documentName);
        
        if ($cursor instanceof BaseLoggableCursor) {
            return new LoggableCursor(
                $dm->getConnection(),
                $coll,
                $dm->getUnitOfWork(),
                $this->documentManager->getClassMetadata($this->documentName),
                $cursor,
                $cursor->getQuery(),
                $cursor->getFields(),
                $dm->getConfiguration()->getRetryQuery(),
                $cursor->getLoggerCallable()
            );
        } else {
            return new Cursor(
                $dm->getConnection(),
                $coll,
                $dm->getUnitOfWork(),
                $this->documentManager->getClassMetadata($this->documentName),
                $cursor,
                $cursor->getQuery(),
                $cursor->getFields(),
                $dm->getConfiguration()->getRetryQuery()
            );
        }
    }
}