<?php
namespace ValuQuery\DoctrineMongoOdm\Listener;
use ValuQuery\QueryBuilder\Event\SimpleSelectorEvent;
use Doctrine\ODM\MongoDB\DocumentManager;
use ArrayAccess;

class FilterListener
{
    
    const STATE_ENABLED = 1;
    
    const STATE_DISABLED = 0;
    
    const PSEUDO_SELECTOR_FILTER = 'filter';
    
    const PSEUDO_SELECTOR_NO_FILTER = 'no-filter';

    /**
     *
     * @var DocumentManager
     */
    protected $documentManager;

    /**
     * Array of filter names
     *
     * @var array
     */
    protected $filters;
    
    /**
     * Original filter states
     * 
     * @var array
     */
    protected $originalFilterStates = [];

    /**
     * Initialize filter listener
     * 
     * Specify document manager and list of filter names
     * that are supported.
     * 
     * @param DocumentManager $dm
     * @param array $filters
     */
    public function __construct(DocumentManager $dm, array $filters)
    {
        $this->setDocumentManager($dm);
        $this->setFilters($filters);
    }

    /**
     * Retrieve names of the supported filters
     * 
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Set names of the supported filters
     * 
     * @param array $filters            
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;
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

    /**
     * Set document manager instance
     * 
     * @param DocumentManager $dm
     */
    public function setDocumentManager(DocumentManager $dm)
    {
        $this->documentManager = $dm;
    }

    /**
     * Enable/disable filter when given event contains correct pseudo selector
     * 
     * @param SimpleSelectorEvent $event
     * @return boolean
     */
    public function __invoke(SimpleSelectorEvent $event)
    {
        $pseudoSelector = $event->getSimpleSelector();
        $query = $event->getQuery();
        
        if ($pseudoSelector->getClassName() === self::PSEUDO_SELECTOR_NO_FILTER
            && $this->hasFilter($pseudoSelector->getClassValue())) {
            $this->disableFilter($pseudoSelector->getClassValue());
            
            return true;
        } elseif ($pseudoSelector->getClassName() === self::PSEUDO_SELECTOR_FILTER
                  && $this->hasFilter($pseudoSelector->getClassValue())) {
            $this->enableFilter($pseudoSelector->getClassValue());
            
            return true;
        }
    }
    
    /**
     * Restore filter states
     * 
     * This method should be called after query to ensure
     * that the filters states are restored back to state
     * before the query.
     */
    public function restoreFilters()
    {
        foreach ($this->originalFilterStates as $filter => $state) {
            if ($state === self::STATE_ENABLED) {
                $this->enableFilter($filter);
            } else {
                $this->disableFilter($filter);
            }
        }
        
        $this->originalFilterStates = [];
    }
    
    /**
     * Disable named filter
     * 
     * @param string $filter
     */
    private function disableFilter($filter)
    {
        if ($this->isEnabledFilter($filter)) {
            $this->getDocumentManager()
                ->getFilterCollection()
                ->disable($filter);
            
            // Remember original state of the filter
            if (!array_key_exists($filter, $this->originalFilterStates)) {
                $this->originalFilterStates[$filter] = self::STATE_ENABLED;
            }
        }
    }
    
    /**
     * Enable named filter
     * 
     * @param string $filter
     */
    private function enableFilter($filter)
    {
        if (!$this->isEnabledFilter($filter)) {
            $this->getDocumentManager()
                ->getFilterCollection()
                ->enable($filter);
            
            // Remember original state of the filter
            if (!array_key_exists($filter, $this->originalFilterStates)) {
                $this->originalFilterStates[$filter] = self::STATE_DISABLED;
            }
        }
    }
    
    /**
     * Test if filter is enabled
     * 
     * @param string $filter
     * @return boolean
     */
    private function isEnabledFilter($filter)
    {
        $enabled = $this->getDocumentManager()->getFilterCollection()->getEnabledFilters();
        return array_key_exists($filter, $enabled);
    }
    
    /**
     * Test if filter exists
     * 
     * @param string $filter
     * @return boolean
     */
    private function hasFilter($filter)
    {
        return in_array($filter, $this->filters);
    }
}