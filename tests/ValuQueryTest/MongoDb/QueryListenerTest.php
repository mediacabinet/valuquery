<?php
namespace ShowellTest\Service;

use PHPUnit_Framework_TestCase as TestCase;
use ValuQuery\MongoDb\QueryListener;
use Zend\EventManager\EventManager;

class QueryListenerTest extends TestCase
{

    private $queryListener;
    
    public function setUp()
    {
        $this->queryListener = new QueryListener();    
    }
    
    public function testAttach()
    {
        $evm = new EventManager();
        $this->queryListener->attach($this->queryListener);
        
        $this->assertEquals(
            [
                'prepareSequence', 
                'combineSequence', 
                'applyElementSelector', 
                'applyIdSelector', 
                'applyRoleSelector', 
                'applyClassSelector', 
                'applyPathSelector', 
                'applyAttributeSelector', 
                'applyPseudoSelector'
            ],
            $evm->getEvents()
        );
    }
    
    public function testDetach()
    {
        $evm = new EventManager();
        $this->queryListener->attach($this->queryListener);
        $this->queryListener->detach($this->queryListener);
        
        $this->assertEquals([], $evm->getEvents());
    }
}