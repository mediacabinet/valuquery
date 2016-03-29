<?php
namespace ValuQueryTest\DoctrineMongoOdm\Listener;

use ValuQueryTest\DoctrineMongoOdm\AbstractTestCase;
use ValuQuery\DoctrineMongoOdm\Listener\FilterListener;
use ValuQuery\Selector\SimpleSelector\Pseudo;
use ValuQuery\QueryBuilder\Event\SimpleSelectorEvent;

class FilterListenerTest extends AbstractTestCase
{
    
    private $filterListener;
    
    protected function setUp()
    {
        parent::setUp();
        
        $this->dm->getConfiguration()->addFilter(
            'public', 
            'ValuQueryTest\TestAsset\Filter\PublicFilter');
        
        $this->dm->getConfiguration()->addFilter(
            'nonremoved', 
            'ValuQueryTest\TestAsset\Filter\NonRemovedFilter');
        
        $this->filterListener = new FilterListener($this->dm, ['public', 'nonremoved']);
    }
    
    public function testConstruct()
    {
        $filterListener = new FilterListener($this->dm, ['public', 'nonremoved']);
        
        $this->assertSame($this->dm, $filterListener->getDocumentManager());
        $this->assertEquals(['public', 'nonremoved'], $filterListener->getFilters());
    }
    
    public function testSetGetFilters()
    {
        $this->filterListener->setFilters(['one', 'two', 'three']);
        $this->assertEquals(['one', 'two', 'three'], $this->filterListener->getFilters());
    }
    
    public function testEnableFilter()
    {
        $pseudoSelector = new Pseudo('filter', 'public');
        $event = new SimpleSelectorEvent($pseudoSelector, new \ArrayObject());
        $this->assertTrue($this->filterListener->__invoke($event));
        $enabled = $this->dm->getFilterCollection()->getEnabledFilters();
        $this->assertEquals(1, sizeof($enabled));
        $this->assertArrayHasKey('public', $enabled);
    }
    
    public function testEnableMultipleFilters()
    {
        $pseudoSelector = new Pseudo('filter', 'public');
        $event = new SimpleSelectorEvent($pseudoSelector, new \ArrayObject());
        $this->filterListener->__invoke($event);
        
        $pseudoSelector = new Pseudo('filter', 'nonremoved');
        $event = new SimpleSelectorEvent($pseudoSelector, new \ArrayObject());
        $this->assertTrue($this->filterListener->__invoke($event));
        
        $enabled = $this->dm->getFilterCollection()->getEnabledFilters();
        $this->assertEquals(2, sizeof($enabled));
        $this->assertArrayHasKey('public', $enabled);
        $this->assertArrayHasKey('nonremoved', $enabled);
    }
    
    public function testUnknownFilterName()
    {
        $pseudoSelector = new Pseudo('filter', 'unknown');
        $event = new SimpleSelectorEvent($pseudoSelector, new \ArrayObject());
        $this->assertNull($this->filterListener->__invoke($event));
        $enabled = $this->dm->getFilterCollection()->getEnabledFilters();
        $this->assertEquals(0, sizeof($enabled));
    }
    
    public function testDisableFilter()
    {
        $this->dm->getFilterCollection()->enable('public');
        
        $pseudoSelector = new Pseudo('no-filter', 'public');
        $event = new SimpleSelectorEvent($pseudoSelector, new \ArrayObject());
        $this->assertTrue($this->filterListener->__invoke($event));
        $enabled = $this->dm->getFilterCollection()->getEnabledFilters();
        $this->assertEquals(0, sizeof($enabled));
    }
    
    public function testEnableAndDisableSameFilter()
    {
        $pseudoSelector = new Pseudo('filter', 'public');
        $event = new SimpleSelectorEvent($pseudoSelector, new \ArrayObject());
        $this->assertTrue($this->filterListener->__invoke($event));
        
        $pseudoSelector = new Pseudo('no-filter', 'public');
        $event = new SimpleSelectorEvent($pseudoSelector, new \ArrayObject());
        $this->assertTrue($this->filterListener->__invoke($event));
        
        $enabled = $this->dm->getFilterCollection()->getEnabledFilters();
        $this->assertEquals(0, sizeof($enabled));
    }
    
    public function testRestorePreviouslyDisabledFilter()
    {
        $pseudoSelector = new Pseudo('filter', 'public');
        $event = new SimpleSelectorEvent($pseudoSelector, new \ArrayObject());
        $this->filterListener->__invoke($event);
        $this->filterListener->restoreFilters();
        
        $enabled = $this->dm->getFilterCollection()->getEnabledFilters();
        $this->assertEquals(0, sizeof($enabled));
    }
    
    public function testRestorePreviouslyEnabledFilter()
    {
        $this->dm->getFilterCollection()->enable('public');
        
        $pseudoSelector = new Pseudo('no-filter', 'public');
        $event = new SimpleSelectorEvent($pseudoSelector, new \ArrayObject());
        $this->filterListener->__invoke($event);
        $this->filterListener->restoreFilters();
    
        $enabled = $this->dm->getFilterCollection()->getEnabledFilters();
        $this->assertEquals(1, sizeof($enabled));
        $this->assertArrayHasKey('public', $enabled);
    }
    
    public function testRestorePreviouslyEnabledAndDisabledFilter()
    {
        $this->dm->getFilterCollection()->enable('public');
    
        $pseudoSelector = new Pseudo('no-filter', 'public');
        $event = new SimpleSelectorEvent($pseudoSelector, new \ArrayObject());
        $this->filterListener->__invoke($event);
        
        $pseudoSelector = new Pseudo('filter', 'nonremoved');
        $event = new SimpleSelectorEvent($pseudoSelector, new \ArrayObject());
        $this->filterListener->__invoke($event);
        
        $this->filterListener->restoreFilters();
    
        $enabled = $this->dm->getFilterCollection()->getEnabledFilters();
        $this->assertEquals(1, sizeof($enabled));
        $this->assertArrayHasKey('public', $enabled);
    }
    
    public function testRestorePreviouslyDisabledFilterAfterItHasBeenEnabledAndDisabled()
    {
        $pseudoSelector = new Pseudo('filter', 'nonremoved');
        $event = new SimpleSelectorEvent($pseudoSelector, new \ArrayObject());
        $this->filterListener->__invoke($event);
        
        $pseudoSelector = new Pseudo('no-filter', 'nonremoved');
        $event = new SimpleSelectorEvent($pseudoSelector, new \ArrayObject());
        $this->filterListener->__invoke($event);
        
        $this->filterListener->restoreFilters();
        
        $enabled = $this->dm->getFilterCollection()->getEnabledFilters();
        $this->assertEquals(0, sizeof($enabled));
        
    }
}
