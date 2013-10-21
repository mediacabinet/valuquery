<?php
namespace ValuQueryTest\MongoDb;

use PHPUnit_Framework_TestCase as TestCase;
use ValuQuery\QueryBuilder\QueryBuilder;
use ValuQuery\Selector\Selector;
use ValuQuery\Selector\Parser\SelectorParser;

class QueryBuilderTest extends TestCase
{
    private $queryBuilder;
    
    public function setUp()
    {
        $this->queryBuilder = new QueryBuilder();
        
        $this->queryBuilder->getEventManager()->attach('*', function() {
            return true;
        });
    }
    
    public function testBuildInvokesCombineSequenceEvent()
    {
        $triggered = 0;
        $event = null;
        
        $this->queryBuilder->getEventManager()->attach('combineSequence', function($e) use(&$triggered, &$event) {
            $triggered++;
            $event = $e;
        });
        
        $selector = $this->parseSelector('a > b');
        $this->queryBuilder->build($selector, new \ArrayObject());
        $this->assertEquals(1, $triggered);
        $this->assertInstanceOf('ValuQuery\QueryBuilder\Event\SelectorEvent', $event);
        $this->assertSame($selector->getLastSequence(), $event->getParam('childSequence'));
        $this->assertSame($selector->getFirstSequence(), $event->getParam('sequence'));
    }
    
    public function testBuildInvokesPrepareQueryEvent()
    {
        $triggered = 0;
        $event = null;
        
        $this->queryBuilder->getEventManager()->attach('prepareQuery', function($e) use(&$triggered, &$event) {
            $triggered++;
            $event = $e;
        });
        
        $selector = $this->parseSelector('a > b');
        $this->queryBuilder->build($selector, new \ArrayObject());
        $this->assertEquals(1, $triggered);
        $this->assertInstanceOf('ValuQuery\QueryBuilder\Event\QueryBuilderEvent', $event);
    }
    
    public function testBuildQuery()
    {
        $this->queryBuilder->getEventManager()->attach('prepareQuery', function($e) {
            $e->setQuery(new \ArrayObject());
        });
        
        $this->queryBuilder->getEventManager()->attach('applyElementSelector', function($e) {
            $q = $e->getQuery();
            $q['vehicle'] = $e->getSimpleSelector()->getValue();
        });
        
        $selector = $this->parseSelector('car');
        $query = $this->queryBuilder->build($selector);
        
        $this->assertInstanceOf('ArrayObject', $query);
        $this->assertEquals(['vehicle' => 'car'], $query->getArrayCopy());
    }
    
    /**
     * @expectedException ValuQuery\QueryBuilder\Exception\InvalidQueryException
     */
    public function testBuildQueryFailsWithQueryAsNonObject()
    {
        $this->queryBuilder->build($this->parseSelector('.videos'), '');
    }
    
    /**
     * @expectedException ValuQuery\QueryBuilder\Exception\InvalidQueryException
     */
    public function testBuildQueryFailsIfPrepareQueryInitializesNonObject()
    {
        $this->queryBuilder->getEventManager()->attach('prepareQuery', function($e) {
            $e->setQuery('');
        });
        
        $this->queryBuilder->build($this->parseSelector('.videos'));
    }
    
    /**
     * @expectedException DomainException
     */
    public function testListenerResponseExceptionIsThrown()
    {
        $qb = new QueryBuilder();
        
        $qb->getEventManager()->attach('*', function($e) {
            return false;
        }, 100000);
        
        $qb->getEventManager()->attach('*', function($e) {
            return new \DomainException();
        });
        
        $qb->build($this->parseSelector('a'), new \ArrayObject());
    }
    
    public function testListenerResponseExceptionIsNotThrownIfAnyResponseIsTruthful()
    {
        $qb = new QueryBuilder();
        
        $qb->getEventManager()->attach('*', function($e) {
            return new \DomainException();
        }, 100000);
        
        $qb->getEventManager()->attach('*', function($e) {
            return true;
        });
    
        $qb->build($this->parseSelector('a'), new \ArrayObject());
    }

    /**
     * @expectedException ValuQuery\QueryBuilder\Exception\SelectorNotSupportedException
     */
    public function testSelectorNotSupportedCausesException()
    {
        $qb = new QueryBuilder();
        $qb->build($this->parseSelector('a'), new \ArrayObject());
    }
    
    protected function parseSelector($pattern)
    {
        $parser = new SelectorParser();
        return $parser->parse($pattern);
    }
}