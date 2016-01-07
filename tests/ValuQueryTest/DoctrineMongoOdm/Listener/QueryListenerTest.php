<?php
namespace ValuQueryTest\DoctrineMongoOdm\Listener;

use ValuQueryTest\DoctrineMongoOdm\AbstractTestCase;
use ValuQuery\DoctrineMongoOdm\Listener\QueryListener;
use ValuQuery\Selector\SimpleSelector\Element;
use ValuQuery\QueryBuilder\Event\QueryBuilderEvent;
use ValuQuery\QueryBuilder\Event\SimpleSelectorEvent;
use ArrayObject;
use ValuQuery\Selector\SimpleSelector\Attribute;
use ValuQuery\Selector\SimpleSelector\Path;
use ValuQueryTest\TestAsset\Dog;
use ValuQuery\Selector\SimpleSelector\Id;
use ValuQuery\Selector\SimpleSelector\Role;
use ValuQueryTest\TestAsset\Category;
use Zend\EventManager\EventManager;
use ValuQuery\Selector\Parser\SelectorParser;
use ValuQuery\QueryBuilder\QueryBuilder;
use ValuQuery\Selector\SimpleSelector\Universal;

class QueryListenerTest extends AbstractTestCase
{
    /**
     * QueryListener
     * @var QueryListener
     */
    private $queryListener;

    protected function setUp()
    {
        parent::setUp();

        $this->queryListener = new QueryListener(
            $this->dm, 'ValuQueryTest\TestAsset\Animal');
    }

    public function testAttach()
    {
        $evm = new EventManager();
        $evm->attach($this->queryListener);

        $events = $evm->getEvents();
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
                'applyPseudoSelector',
                'finalizeQuery',
            ],
            $events
        );
    }

    public function testDetach()
    {
        $evm = new EventManager();
        $evm->attach($this->queryListener);
        $evm->detach($this->queryListener);

        $this->assertEquals([], $evm->getEvents());
    }

    public function testFinalizeQuery()
    {
        $query = new ArrayObject();
        $query['__doctrine_mongodb_odm'] = true;

        $event = new QueryBuilderEvent();
        $event->setQuery($query);
        $this->queryListener->finalizeQuery($event);

        $this->assertArrayNotHasKey('__doctrine_mongodb_odm', $query->getArrayCopy());
    }

    public function testApplyUniversalSelector()
    {
        $query = new ArrayObject(['query' => []]);
        $selector = new Universal();
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyUniversalSelector($event));
        $query = $query->getArrayCopy();
        $this->assertEquals([], $query['query']);
    }

    public function testApplyElementSelector()
    {
        $query = new ArrayObject();
        $selector = new Element('cat');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyElementSelector($event));
        $query = $query->getArrayCopy();
        $this->assertEquals(['type' => 'cat'], $query['query']);
    }

    public function testApplyUnknownElementSelector()
    {
        $query = new ArrayObject();
        $selector = new Element('mouse');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertInstanceOf(
            'ValuQuery\DoctrineMongoOdm\Exception\UnknownElementException',
            $this->queryListener->applyElementSelector($event));
    }

    public function testApplyPathSelector()
    {
        $query = new ArrayObject();
        $selector = new Path(['Chordata', 'Mammalia', 'Carnivora', 'Felidae', 'Felis', 'F. catus']);
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyPathSelector($event));
        $query = $query->getArrayCopy();
        $this->assertEquals(['path' => '/Chordata/Mammalia/Carnivora/Felidae/Felis/F. catus'], $query['query']);
    }

    public function testApplyPathSelectorWithWildcard()
    {
        $query = new ArrayObject();
        $selector = new Path(['Chordata', 'Mammalia', '*']);
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyPathSelector($event));
        $query = $query->getArrayCopy();
        $this->assertEquals(['path' => ['$regex' => '^/Chordata/Mammalia/.*$']], $query['query']);
    }

    public function testApplyEmptyPathSelector()
    {
        $query = new ArrayObject();
        $selector = new Path([]);
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyPathSelector($event));
        $query = $query->getArrayCopy();
        $this->assertEquals(['path' => '/'], $query['query']);
    }

    public function testApplyPathSelectorWithIdSubSelector()
    {
        $queryListener = new QueryListener($this->queryListener->getDocumentManager(), 'ValuQueryTest\TestAsset\Category');

        $category = new Category();
        $category->path = '/Dogs';

        $dm = $this->queryListener->getDocumentManager();
        $dm->persist($category);
        $dm->flush();

        $query = new ArrayObject();
        $selector = new Path([new Id($category->id)]);
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($queryListener->applyPathSelector($event));
        $query = $query->getArrayCopy();
        $this->assertEquals(['path' => '/Dogs'], $query['query']);
    }

    public function testApplyPathSelectorWithRoleSubSelector()
    {
        $dog = new Dog();
        $dog->path = '/Shephards';
        $dog->roles = ['shephards'];

        $dm = $this->queryListener->getDocumentManager();
        $dm->persist($dog);
        $dm->flush();

        $query = new ArrayObject();
        $selector = new Path([new Role('shephards')]);
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyPathSelector($event));
        $query = $query->getArrayCopy();
        $this->assertEquals(['path' => '/Shephards'], $query['query']);
    }

    /**
     * @expectedException ValuQuery\DoctrineMongoOdm\Path\Exception\IllegalPathSelectorException
     */
    public function testApplyPathSelectorWithIllegalSubSelector()
    {
        $query = new ArrayObject();
        $selector = new Path([new Path([])]);
        $event = new SimpleSelectorEvent($selector, $query);
        $this->queryListener->applyPathSelector($event);
    }

    public function testApplyPathSelectorWithNonMatchingSubSelector()
    {
        $query = new ArrayObject();
        $selector = new Path([new Role('x')]);
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyPathSelector($event));
        $query = $query->getArrayCopy();
        $this->assertEquals(['_id' => false], $query['query']);
    }

    public function testQueryBySimpleReference()
    {
        $query = new ArrayObject();
        $selector = new Attribute('sound', Attribute::OPERATOR_EQUALS, 'abc');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyAttributeSelector($event));
        $query = $query->getArrayCopy();
        $this->assertEquals(['sound' => 'abc'], $query['query']);
    }

    public function testQueryByDBRefReference()
    {
        $query = new ArrayObject();
        $selector = new Attribute('limbs', Attribute::OPERATOR_IN_LIST, '526fa3a24c1680730b000000');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyAttributeSelector($event));
        $query = $query->getArrayCopy();

        $this->assertArrayHasKey('limbs.$id', $query['query']);
        $this->assertArrayHasKey('$in', $query['query']['limbs.$id']);
        $this->assertInternalType('array', $query['query']['limbs.$id']['$in']);
        $this->assertInstanceOf('MongoId', $query['query']['limbs.$id']['$in'][0]);
    }

    public function testQueryByDBRefReferenceFromConcreteSubclass()
    {
        $this->queryListener->setDefaultDocumentName('ValuQueryTest\TestAsset\Cat');
        $this->testQueryByDBRefReference();
    }

    public function testAttributeQueryToConcreteClassUsingInheritedField()
    {
        $this->queryListener->setDefaultDocumentName('ValuQueryTest\TestAsset\Cat');

        $this->assertAttributeQueryEquals(
            ['name' => 'Siamese'],
            'name', Attribute::OPERATOR_EQUALS, 'Siamese'
        );
    }

    public function testAttributeQueryByDateField()
    {
        $this->assertAttributeQueryEquals(
            ['createdAt' => new \MongoDate(strtotime("2010-01-15 00:00:00"))],
            'createdAt', Attribute::OPERATOR_EQUALS, "2010-01-15 00:00:00"
        );
    }

    public function testAttributeQueryByBooleanField()
    {
        $this->assertAttributeQueryEquals(
            ['canFly' => false],
            'canFly', Attribute::OPERATOR_EQUALS, false
        );

        $this->assertAttributeQueryEquals(
            ['canFly' => true],
            'canFly', Attribute::OPERATOR_EQUALS, true
        );
    }

    public function testAttributeQueryUsingEmbeddedField()
    {
        $this->assertAttributeQueryEquals(
            ['head.replacable' => false],
            'head.replacable', Attribute::OPERATOR_EQUALS, false
        );
    }

    public function testAttributeQueryUsingMappedField()
    {
        $this->assertAttributeQueryEquals(
            ['breedUw' => false],
            'isAbleToBreedUnderWater', Attribute::OPERATOR_EQUALS, false
        );
    }

    public function testAttributeQueryUsingMappedFieldInEmbeddedDocument()
    {
        $this->assertAttributeQueryEquals(
            ['head.isInMainCirc' => false],
            'head.isPartOfMainBloodCirculation', Attribute::OPERATOR_EQUALS, false
        );
    }

    public function testBuildQuery()
    {
        $qb = new QueryBuilder();
        $qb->getEventManager()->attach($this->queryListener);

        $selector = SelectorParser::parseSelector('dog#526fa3a24c1680730b000000.long-hair.black[maxAge>12]');
        $query = $qb->build($selector);

        $this->assertEquals([
            'type' => 'dog',
            '_id' => '526fa3a24c1680730b000000',
            'classes' => ['$in' => ['long-hair', 'black']],
            'maxAge' => ['$gt' => 12]
        ], $query['query']);
    }

    public function testBuildPathQuery()
    {
        $category = new Category();
        $category->path = '/Animals';
        $category->roles = ['animals'];

        $dm = $this->queryListener->getDocumentManager();
        $dm->persist($category);
        $dm->flush();

        $queryListener = new QueryListener($this->queryListener->getDocumentManager(), 'ValuQueryTest\TestAsset\Category');

        $category = new Category();
        $category->path = '/Animals/Dogs and cats/Height > Length';

        $qb = new QueryBuilder();
        $qb->getEventManager()->attach($queryListener);

        $selector = SelectorParser::parseSelector('/$animals/Docs\\ and\\ cats/Height\\ \\>\\ Length/*');
        $query = $qb->build($selector);

        $this->assertEquals([
            'path' => ['$regex' => '^/Animals/Docs and cats/Height \> Length/.*$'],
        ], $query['query']);
    }

    private function assertAttributeQueryEquals($expected, $field, $operator, $condition)
    {
        $query = new ArrayObject();
        $this->assertTrue($this->makeAttributeQuery($query, $field, $operator, $condition));
        $query = $query->getArrayCopy();

        $this->assertEquals($expected, $query['query']);
    }

    private function makeAttributeQuery(ArrayObject $query, $field, $operator, $condition)
    {
        $selector = new Attribute($field, $operator, $condition);
        $event = new SimpleSelectorEvent($selector, $query);
        return $this->queryListener->applyAttributeSelector($event);
    }
}
