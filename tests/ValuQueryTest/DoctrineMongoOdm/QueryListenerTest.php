<?php
namespace ValuQueryTest\MongoDb;

use PHPUnit_Framework_TestCase as TestCase;
use ValuQuery\DoctrineMongoOdm\QueryListener;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\Common\EventManager as Evm;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;
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

class QueryListenerTest extends TestCase
{
    private $queryListener;
    
    public function setUp()
    {

        $evm = new Evm();
        
        /* @var $driver MappingDriver */
        $driver = AnnotationDriver::create();
        AnnotationDriver::registerAnnotationClasses();
        
        $configuration = new Configuration();
        $configuration->addDocumentNamespace('valuquerytest', __DIR__ . '/../TestAsset');
        $configuration->setProxyDir(__DIR__ . '/../../resources/proxy');
        $configuration->setHydratorDir(__DIR__ . '/../../resources/hydrator');
        $configuration->setAutoGenerateHydratorClasses(true);
        $configuration->setAutoGenerateProxyClasses(true);
        $configuration->setProxyNamespace('ValuQueryTestProxy');
        $configuration->setHydratorNamespace('ValuQueryTestHydrator');
        $configuration->setDefaultDB('valuquerytest');
        $configuration->setMetadataCacheImpl(new ArrayCache());
        $configuration->setMetadataDriverImpl($driver);
        
        $connection = new Connection('mongodb://localhost:27017', [], $configuration, $evm);
        
        $dm = DocumentManager::create($connection, $configuration, $evm);
        $this->queryListener = new QueryListener($dm, 'ValuQueryTest\TestAsset\Animal');
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
        $this->assertEquals(['path' => ['$regex' => '^/Chordata/Mammalia/Carnivora/Felidae/Felis/F\. catus$']], $query['query']);
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
        $this->assertEquals(['path' => ['$regex' => '^/Dogs$']], $query['query']);
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
        $this->assertEquals(['path' => ['$regex' => '^/Shephards$']], $query['query']);
    }
    
    /**
     * @expectedException ValuQuery\DoctrineMongoOdm\Exception\IllegalPathSelectorException
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
        $selector = new Attribute('root', Attribute::OPERATOR_EQUALS, 'abc');
        $event = new SimpleSelectorEvent($selector, $query);
        $this->assertTrue($this->queryListener->applyAttributeSelector($event));
        $query = $query->getArrayCopy();
        $this->assertEquals(['root' => 'abc'], $query['query']);
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