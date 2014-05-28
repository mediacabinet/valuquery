<?php
namespace ValuQueryTest\MongoDb;

use PHPUnit_Framework_TestCase as TestCase;
use ValuQuery\MongoDb\QueryListener;
use Zend\EventManager\EventManager;
use ValuQuery\QueryBuilder\Event\QueryBuilderEvent;
use ValuQuery\QueryBuilder\Event\SimpleSelectorEvent;
use ValuQuery\Selector\SimpleSelector\Element;
use ValuQuery\Selector\SimpleSelector\Id;
use ArrayObject;
use ValuQuery\Selector\SimpleSelector\Role;
use ValuQuery\Selector\SimpleSelector\ClassName;
use ValuQuery\Selector\SimpleSelector\Path;
use ValuQuery\Selector\SimpleSelector\Attribute;
use ValuQuery\Selector\SimpleSelector\Pseudo;
use ValuQuery\Selector\SimpleSelector\Pseudo\Sort;
use ValuQuery\Selector\SimpleSelector\Universal;

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
        $evm->attach($this->queryListener);
        
        $this->assertEquals(
            [
                'prepareQuery', 
                'combineSequence', 
                'applyUniversalSelector', 
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
        $evm->attach($this->queryListener);
        $evm->detach($this->queryListener);
        
        $this->assertEquals([], $evm->getEvents());
    }
    
    public function testPrepareQuery()
    {
        $event = new QueryBuilderEvent();
        $this->queryListener->prepareQuery($event);
        
        $this->assertInstanceOf('ArrayObject', $event->getQuery());
    }
    
    public function testApplyUniversalSelector()
    {
        $selector = new Universal();
        $event = new SimpleSelectorEvent($selector, new ArrayObject());
        $this->assertTrue($this->queryListener->applyUniversalSelector($event));
    }

    public function testApplyElementSelector()
    {
        $selector = new Element('name');
        $event = new SimpleSelectorEvent($selector, new ArrayObject());
        $this->assertNull($this->queryListener->applyElementSelector($event));
    }
    
    public function testApplyIdSelector()
    {
        $query = new ArrayObject();
        $selector = new Id('abcd');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyIdSelector($event));
        
        $this->assertEquals(['query' => ['_id' => 'abcd']], $query->getArrayCopy());
    }
    
    public function testApplyRoleSelector()
    {
        $query = new ArrayObject();
        $selector = new Role('root');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyRoleSelector($event));
        
        $this->assertEquals(['query' => ['roles' => ['$in' => ['root']]]], $query->getArrayCopy());
    }
    
    public function testApplyMultipleRoleSelectors()
    {
        $query = new ArrayObject();
        
        $selector = new Role('root');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyRoleSelector($event));
        
        $selector = new Role('master');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyRoleSelector($event));
        
        $this->assertEquals(['query' => ['roles' => ['$in' => ['root', 'master']]]], $query->getArrayCopy());
    }
    
    public function testApplyRoleSelectorReturnsNullIfRoleFieldNotDefined()
    {
        $query = new ArrayObject();
        $selector = new Role('root');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->queryListener->setRoleField(null);
        $this->assertNull($this->queryListener->applyRoleSelector($event));
    }
    
    public function testApplyClassSelector()
    {
        $query = new ArrayObject();
        $selector = new ClassName('video');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyClassSelector($event));
    
        $this->assertEquals(['query' => ['classes' => ['$in' => ['video']]]], $query->getArrayCopy());
    }
    
    public function testApplyMultipleClassSelectors()
    {
        $query = new ArrayObject();
    
        $selector = new ClassName('video');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyClassSelector($event));
    
        $selector = new ClassName('hd');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyClassSelector($event));
    
        $this->assertEquals(['query' => ['classes' => ['$in' => ['video', 'hd']]]], $query->getArrayCopy());
    }
    
    public function testApplyClassSelectorReturnsNullIfClassFieldNotDefined()
    {
        $query = new ArrayObject();
        $selector = new ClassName('video');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->queryListener->setClassField(null);
        $this->assertNull($this->queryListener->applyClassSelector($event));
    }
    
    public function testApplyPathSelector()
    {
        $query = new ArrayObject();
        $selector = new Path(['media', 'videos']);
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyPathSelector($event));
    
        $this->assertEquals(['query' => ['path' => '/media/videos']], $query->getArrayCopy());
    }

    public function testApplyPathSelectorReturnsNullIfPathFieldNotDefined()
    {
        $query = new ArrayObject();
        $selector = new Path(['media', 'videos']);
        $event = new SimpleSelectorEvent($selector, $query);
        $this->queryListener->setPathField(null);
        $this->assertNull($this->queryListener->applyPathSelector($event));
    }
    
    public function testApplyAttributeSelectorStringEquals()
    {
        $selector = new Attribute('name', Attribute::OPERATOR_EQUALS, 'John');
        $this->assertEquals(['query' => ['name' => 'John']], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorBooleanEquals()
    {
        $selector = new Attribute('retired', Attribute::OPERATOR_EQUALS, true);
        $this->assertEquals(['query' => ['retired' => true]], $this->applyAttributeSelector($selector));
        
        $selector = new Attribute('retired', Attribute::OPERATOR_EQUALS, false);
        $this->assertEquals(['query' => ['retired' => false]], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorIntegerEquals()
    {
        $selector = new Attribute('age', Attribute::OPERATOR_EQUALS, 0);
        $this->assertEquals(['query' => ['age' => 0]], $this->applyAttributeSelector($selector));
        
        $selector = new Attribute('age', Attribute::OPERATOR_EQUALS, 15);
        $this->assertEquals(['query' => ['age' => 15]], $this->applyAttributeSelector($selector));
        
        $selector = new Attribute('temperature', Attribute::OPERATOR_EQUALS, -25);
        $this->assertEquals(['query' => ['temperature' => -25]], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorFloatEquals()
    {
        $selector = new Attribute('grade', Attribute::OPERATOR_EQUALS, 1.5);
        $this->assertEquals(['query' => ['grade' => 1.5]], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorNotEquals()
    {
        $selector = new Attribute('name', Attribute::OPERATOR_NOT_EQUALS, 'John');
        $this->assertEquals(['query' => ['name' => ['$ne' => 'John']]], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorExists()
    {
        $selector = new Attribute('lastName');
        $this->assertEquals(['query' => ['lastName' => ['$exists' => true]]], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorGreaterThan()
    {
        $selector = new Attribute('age', Attribute::OPERATOR_GREATER_THAN, 15);
        $this->assertEquals(['query' => ['age' => ['$gt' => 15]]], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorGreaterThanOrEqual()
    {
        $selector = new Attribute('age', Attribute::OPERATOR_GREATER_THAN_OR_EQUAL, 15);
        $this->assertEquals(['query' => ['age' => ['$gte' => 15]]], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorLessThan()
    {
        $selector = new Attribute('age', Attribute::OPERATOR_LESS_THAN, 15);
        $this->assertEquals(['query' => ['age' => ['$lt' => 15]]], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorLessThanOrEqual()
    {
        $selector = new Attribute('age', Attribute::OPERATOR_LESS_THAN_OR_EQUAL, 15);
        $this->assertEquals(['query' => ['age' => ['$lte' => 15]]], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorInList()
    {
        $hobbies = ' sailing chilling  relaxing ';
        $selector = new Attribute('hobbies', Attribute::OPERATOR_IN_LIST, $hobbies);
        
        $this->assertEquals(['query' => ['hobbies' => ['$in' => ['sailing', 'chilling', 'relaxing']]]], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorRegExp()
    {
        $selector = new Attribute('name', Attribute::OPERATOR_REG_EXP, 'john.*smith');
        $this->assertEquals(['query' => ['name' => ['$regex' => 'john.*smith']]], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorSubstrMatch()
    {
        $selector = new Attribute('ip', Attribute::OPERATOR_SUBSTR_MATCH, '.221.');
        $this->assertEquals(['query' => ['ip' => ['$regex' => ".*\\.221\\..*"]]], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorPrefixMatch()
    {
        $selector = new Attribute('ip', Attribute::OPERATOR_SUBSTR_PREFIX, '168.');
        $this->assertEquals(['query' => ['ip' => ['$regex' => "^168\\."]]], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorSuffixMatch()
    {
        $selector = new Attribute('ip', Attribute::OPERATOR_SUBSTR_SUFFIX, '.2');
        $this->assertEquals(['query' => ['ip' => ['$regex' => "\\.2$"]]], $this->applyAttributeSelector($selector));
    }
    
    public function testApplyAttributeSelectorWithUnknownOperator()
    {
        $selector = new Attribute('x', 'Y', 'z');
        
        $query = new ArrayObject();
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertInstanceOf(
            'ValuQuery\MongoDb\Exception\UnknownOperatorException', 
            $this->queryListener->applyAttributeSelector($event));
    }
    
    public function testApplyPseudoSelectorSortDescending()
    {
        $selector = new Sort('name', 'desc');
        $this->assertEquals(['sort' => ['name' => -1]], $this->applyPseudoSelector($selector));
    }
    
    public function testApplyPseudoSelectorSortAscending()
    {
        $selector = new Sort('name', 'ASC');
        $this->assertEquals(['sort' => ['name' => 1]], $this->applyPseudoSelector($selector));
    }
    
    public function testApplyPseudoSelectorSortMultiple()
    {
        $selector1 = new Sort('name', 'ASC');
        $selector2 = new Sort('age', 'asc');
        $selector3 = new Sort('name', 'desc');
        
        $query = new ArrayObject();
        
        $event = new SimpleSelectorEvent($selector1, $query);
        $this->assertTrue($this->queryListener->applyPseudoSelector($event));
        
        $event = new SimpleSelectorEvent($selector2, $query);
        $this->assertTrue($this->queryListener->applyPseudoSelector($event));
        
        $event = new SimpleSelectorEvent($selector3, $query);
        $this->assertTrue($this->queryListener->applyPseudoSelector($event));
        
        $this->assertEquals(['sort' => ['name' => -1, 'age' => 1]], $event->getQuery()->getArrayCopy());
    }
    
    public function testApplyPseudoSelectorLimit()
    {
        $selector = new Pseudo('limit', 0);
        $this->assertEquals(['limit' => 0], $this->applyPseudoSelector($selector));
        
        $selector = new Pseudo('limit', 25);
        $this->assertEquals(['limit' => 25], $this->applyPseudoSelector($selector));
        
        $selector = new Pseudo('limit', -1);
        $this->assertEquals(['limit' => -1], $this->applyPseudoSelector($selector));
        
        $selector = new Pseudo('limit', 'a');
        $this->assertEquals(['limit' => 0], $this->applyPseudoSelector($selector));
        
        $selector = new Pseudo('limit', '2');
        $this->assertEquals(['limit' => 2], $this->applyPseudoSelector($selector));
    }
    
    public function testApplyPseudoSelectorOffset()
    {
        $selector = new Pseudo('offset', 0);
        $this->assertEquals(['skip' => 0], $this->applyPseudoSelector($selector));
        
        $selector = new Pseudo('offset', 25);
        $this->assertEquals(['skip' => 25], $this->applyPseudoSelector($selector));
        
        $selector = new Pseudo('offset', -1);
        $this->assertEquals(['skip' => -1], $this->applyPseudoSelector($selector));
        
        $selector = new Pseudo('offset', 'a');
        $this->assertEquals(['skip' => 0], $this->applyPseudoSelector($selector));
        
        $selector = new Pseudo('offset', '2');
        $this->assertEquals(['skip' => 2], $this->applyPseudoSelector($selector));
        
        $selector = new Pseudo('startingFrom', 5);
        $this->assertEquals(['skip' => 5], $this->applyPseudoSelector($selector));
        
        $selector = new Pseudo('skip', 5);
        $this->assertEquals(['skip' => 5], $this->applyPseudoSelector($selector));
    }
    
    public function testApplyUnknownPseudoSelectorReturnsNull()
    {
        $selector = new Pseudo('x', 'y');
        $query = new ArrayObject();
        $event = new SimpleSelectorEvent($selector, $query);
        
        $this->assertNull($this->queryListener->applyPseudoSelector($event));
    }
    
    public function testApplyMultipleSelectors()
    {
        $query = new ArrayObject();
        
        $selector = new Id(123);
        $event = new SimpleSelectorEvent($selector, $query);
        $this->queryListener->applyIdSelector($event);
        
        $selector = new ClassName('video');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->queryListener->applyClassSelector($event);
        
        $selector = new Path(['media', 'video']);
        $event = new SimpleSelectorEvent($selector, $query);
        $this->queryListener->applyPathSelector($event);
        
        $selector = new ClassName('sd');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->queryListener->applyClassSelector($event);
        
        $selector = new Role('intro');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->queryListener->applyRoleSelector($event);
        
        $selector = new Attribute('name', Attribute::OPERATOR_EQUALS, 'introduction.mp4');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->queryListener->applyAttributeSelector($event);
        
        $selector = new Attribute('format', Attribute::OPERATOR_IN_LIST, 'mp4 mov');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->queryListener->applyAttributeSelector($event);
        
        $selector = new Pseudo('offset', 1);
        $event = new SimpleSelectorEvent($selector, $query);
        $this->queryListener->applyPseudoSelector($event);
        
        $selector = new Pseudo('limit', 2);
        $event = new SimpleSelectorEvent($selector, $query);
        $this->queryListener->applyPseudoSelector($event);
        
        $selector = new Sort('length', 'desc');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->queryListener->applyPseudoSelector($event);
        
        $q = $query->getArrayCopy();
        $this->assertEquals([
            'query' => [
                '_id' => 123,
                'classes' => ['$in' => ['video', 'sd']],
                'path' => '/media/video',
                'roles' => ['$in' => ['intro']],
                'name' => 'introduction.mp4',
                'format' => ['$in' => ['mp4', 'mov']],
            ],
            'skip' => 1,
            'limit' => 2,
            'sort' => ['length' => -1]
        ], $q);
    }
    
    private function applyAttributeSelector($selector)
    {
        $query = new ArrayObject();
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyAttributeSelector($event));
        
        return $query->getArrayCopy();
    }
    
    private function applyPseudoSelector($selector)
    {
        $query = new ArrayObject();
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyPseudoSelector($event));
        
        return $query->getArrayCopy();
    }
}