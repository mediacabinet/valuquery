<?php
namespace ValuQueryTest\QueryBuilder;

use PHPUnit_Framework_TestCase as TestCase;
use ValuQuery\QueryBuilder\QueryBuilder;
use ValuQuery\Selector\Selector;
use ValuQuery\Selector\Parser\SelectorParser;

class QueryBuilderTest extends TestCase
{
    private $queryBuilder;
    
    protected function setUp()
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
        $this->queryBuilder->build($selector);
        $this->assertEquals(1, $triggered);
        $this->assertInstanceOf('ValuQuery\QueryBuilder\Event\SelectorEvent', $event);
        $this->assertSame($selector->getFirstSequence(), $event->getSequence());
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
        $this->queryBuilder->build($selector);
        $this->assertEquals(1, $triggered);
        $this->assertInstanceOf('ValuQuery\QueryBuilder\Event\QueryBuilderEvent', $event);
    }
    
    public function testBuildInvokesFinalizeQueryEvent()
    {
        $this->queryBuilder->getEventManager()->attach('finalizeQuery', function($e) {
            $q = $e->getQuery();
            $q['finalized'] = true;
        });
        
        $selector = $this->parseSelector('a > b');
        $query = $this->queryBuilder->build($selector);
        $this->assertTrue($query['finalized']);
    }
    
    public function testBuildQuery()
    {
        $this->queryBuilder->getEventManager()->attach('prepareQuery', function($e) {
            $e->setQuery(new \ArrayObject());
        });
        
        $this->queryBuilder->getEventManager()->attach('applyElementSelector', function($e) {
            $q = $e->getQuery();
            $q['vehicle'] = $e->getSimpleSelector()->getElement();
        });
        
        $selector = $this->parseSelector('car');
        $query = $this->queryBuilder->build($selector);
        
        $this->assertInstanceOf('ArrayObject', $query);
        $this->assertEquals(['vehicle' => 'car'], $query->getArrayCopy());
    }
    
    public function testBuildQueryWithChildSequences()
    {
        $this->queryBuilder->getEventManager()->attach('prepareQuery', function($e) {
            $e->setQuery(new \ArrayObject());
        });
        
        $this->queryBuilder->getEventManager()->attach('applyClassSelector', function($e) {
            $q = $e->getQuery();
            $q['class'] = $e->getSimpleSelector()->getCondition();
            return true;
        });
        
        $this->queryBuilder->getEventManager()->attach('combineSequence', function($e) {
            $q = $e->getQuery();
            $sequence = $e->getSequence();
            $combinator = $sequence->getChildCombinator();
            
            if ($combinator === Selector::COMBINATOR_CHILD) {
                $q['category'] = $sequence->__toString();
                return true;
            } else if ($combinator === Selector::COMBINATOR_DESCENDENT) {
                $q['type'] = $sequence->__toString();
                return true;
            }
        });
    
        $selector = $this->parseSelector('vehicles>car .crossover');
        $query = $this->queryBuilder->build($selector);

        $this->assertInstanceOf('ArrayObject', $query);
        $this->assertEquals(['class' => 'crossover', 'type' => 'car', 'category' => 'vehicles'], $query->getArrayCopy());
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
        
        $qb->build($this->parseSelector('a'));
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
    
        $qb->build($this->parseSelector('a'));
    }

    /**
     * @expectedException ValuQuery\QueryBuilder\Exception\SelectorNotSupportedException
     */
    public function testSelectorNotSupportedCausesException()
    {
        $qb = new QueryBuilder();
        $qb->build($this->parseSelector('a'));
    }
    
    protected function parseSelector($pattern)
    {
        $parser = new SelectorParser();
        return $parser->parse($pattern);
    }
}