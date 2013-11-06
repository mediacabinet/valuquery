<?php
namespace ValuQueryTest\DoctrineMongoOdm;

use ValuQuery\DoctrineMongoOdm\QueryHelper;

/**
 * QueryHelper test case.
 */
class QueryHelperTest extends AbstractTestCase
{

    /**
     * @var QueryHelper
     */
    private $queryHelper;

    /**
     * Prepares the environment before running a test.
     */
    public function setUp()
    {
        parent::setUp();
        
        $this->queryHelper = new QueryHelper($this->dm, 'ValuQueryTest\TestAsset\Animal');
        $this->queryHelper->enableIdDetection(QueryHelper::ID_UUID5);
    }

    /**
     * Cleans up the environment after running a test.
     */
    public function tearDown()
    {
        $this->queryHelper = null;
        parent::tearDown();
    }

    /**
     * Tests queryHelper->__construct()
     */
    public function test__construct()
    {
        $this->assertSame($this->dm, $this->queryHelper->getDocumentManager());
        $this->assertEquals('ValuQueryTest\TestAsset\Animal', $this->queryHelper->getDocumentName());
    }

    public function testQueryUsingWildcard()
    {
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger']);
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        
        $result = $this->queryHelper->query('*');
        $this->assertInstanceOf('Doctrine\ODM\MongoDB\Cursor', $result);
        $this->assertEquals(2, $result->count());
        
        $result->next();
        $this->assertSame($tiger, $result->current());
        
        $result->next();
        $this->assertSame($lion, $result->current());
    }
    
    public function testQueryById()
    {
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger']);
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        
        $result = $this->queryHelper->query((string) $tiger->id);
        $result->next();
        $this->assertSame($tiger, $result->current());
    }
    
    public function testQueryBySelector()
    {
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger', 'classes' => ['ultrafast']]);
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        $leopard = $this->createTestEntity('Cat',['name' => 'Leopard', 'classes' => ['ultrafast']]);
        
        $result = $this->queryHelper->query('.ultrafast');
        $this->assertEquals(2, $result->count());
        
        $result->next();
        $this->assertSame($tiger, $result->current());
        
        $result->next();
        $this->assertSame($leopard, $result->current());
    }
    
    public function testQueryField()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        $tomcat = $this->createTestEntity('Cat',['name' => 'Tomcat']);
        
        $result = $this->queryHelper->query('[name~=Lion Tomcat]', 'name');
        $this->assertEquals(['Lion', 'Tomcat'], $result);
    }
    
    public function testQueryManyFields()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion', 'maxAge' => 15, 'createdAt' => new \DateTime()]);
        $tomcat = $this->createTestEntity('Cat',['name' => 'Tomcat', 'maxAge' => 20]);
        
        $result = $this->queryHelper->query('[maxAge>=15]', ['name' => true, 'maxAge' => true, 'id' => true, 'createdAt' => true]);
        $this->assertEquals([['name' => 'Lion', 'maxAge' => 15],['name' => 'Tomcat', 'maxAge' => 20]], $result);
    }
    
    /**
     * @expectedException ValuQuery\DoctrineMongoOdm\Exception\UnknownElementException
     */
    public function testQueryDoesntMatchByIdWhenIdDetectionIsDisabled()
    {
        $this->queryHelper->disableIdDetection();
        $this->queryHelper->query('59435d0b854a31bbad8da7aadf8ed385');
    }

    /**
     * Tests queryHelper->queryOne()
     */
    public function testQueryOne()
    {
        // TODO Auto-generated queryHelperTest->testQueryOne()
        $this->markTestIncomplete("queryOne test not implemented");
        
        $this->queryHelper->queryOne(/* parameters */);
    }

    /**
     * Tests queryHelper->count()
     */
    public function testCount()
    {
        // TODO Auto-generated queryHelperTest->testCount()
        $this->markTestIncomplete("count test not implemented");
        
        $this->queryHelper->count(/* parameters */);
    }

    /**
     * Tests queryHelper->exists()
     */
    public function testExists()
    {
        // TODO Auto-generated queryHelperTest->testExists()
        $this->markTestIncomplete("exists test not implemented");
        
        $this->queryHelper->exists(/* parameters */);
    }

    /**
     * Tests queryHelper->findBySelector()
     */
    public function testFindBySelector()
    {
        // TODO Auto-generated queryHelperTest->testFindBySelector()
        $this->markTestIncomplete("findBySelector test not implemented");
        
        $this->queryHelper->findBySelector(/* parameters */);
    }

    /**
     * Tests queryHelper->findOneBySelector()
     */
    public function testFindOneBySelector()
    {
        // TODO Auto-generated queryHelperTest->testFindOneBySelector()
        $this->markTestIncomplete("findOneBySelector test not implemented");
        
        $this->queryHelper->findOneBySelector(/* parameters */);
    }

    /**
     * Tests queryHelper->countBySelector()
     */
    public function testCountBySelector()
    {
        // TODO Auto-generated queryHelperTest->testCountBySelector()
        $this->markTestIncomplete("countBySelector test not implemented");
        
        $this->queryHelper->countBySelector(/* parameters */);
    }

    /**
     * Tests queryHelper->findByArray()
     */
    public function testFindByArray()
    {
        // TODO Auto-generated queryHelperTest->testFindByArray()
        $this->markTestIncomplete("findByArray test not implemented");
        
        $this->queryHelper->findByArray(/* parameters */);
    }

    /**
     * Tests queryHelper->findOneByArray()
     */
    public function testFindOneByArray()
    {
        // TODO Auto-generated queryHelperTest->testFindOneByArray()
        $this->markTestIncomplete("findOneByArray test not implemented");
        
        $this->queryHelper->findOneByArray(/* parameters */);
    }

    /**
     * Tests queryHelper->countByArray()
     */
    public function testCountByArray()
    {
        // TODO Auto-generated queryHelperTest->testCountByArray()
        $this->markTestIncomplete("countByArray test not implemented");
        
        $this->queryHelper->countByArray(/* parameters */);
    }

    /**
     * Tests queryHelper->isEmptyQueryParam()
     */
    public function testIsEmptyQueryParam()
    {
        // TODO Auto-generated queryHelperTest->testIsEmptyQueryParam()
        $this->markTestIncomplete("isEmptyQueryParam test not implemented");
        
        $this->queryHelper->isEmptyQueryParam(/* parameters */);
    }

    /**
     * Tests queryHelper->enableIdDetection()
     */
    public function testEnableIdDetection()
    {
        // TODO Auto-generated queryHelperTest->testEnableIdDetection()
        $this->markTestIncomplete("enableIdDetection test not implemented");
        
        $this->queryHelper->enableIdDetection(/* parameters */);
    }

    /**
     * Tests queryHelper->disableIdDetection()
     */
    public function testDisableIdDetection()
    {
        // TODO Auto-generated queryHelperTest->testDisableIdDetection()
        $this->markTestIncomplete("disableIdDetection test not implemented");
        
        $this->queryHelper->disableIdDetection(/* parameters */);
    }

    /**
     * Tests queryHelper->getDocumentManager()
     */
    public function testGetDocumentManager()
    {
        // TODO Auto-generated queryHelperTest->testGetDocumentManager()
        $this->markTestIncomplete("getDocumentManager test not implemented");
        
        $this->queryHelper->getDocumentManager(/* parameters */);
    }

    /**
     * Tests queryHelper->getQueryBuilder()
     */
    public function testGetQueryBuilder()
    {
        // TODO Auto-generated queryHelperTest->testGetQueryBuilder()
        $this->markTestIncomplete("getQueryBuilder test not implemented");
        
        $this->queryHelper->getQueryBuilder(/* parameters */);
    }

    /**
     * Tests queryHelper->getDefaultQueryListener()
     */
    public function testGetDefaultQueryListener()
    {
        // TODO Auto-generated queryHelperTest->testGetDefaultQueryListener()
        $this->markTestIncomplete(
                "getDefaultQueryListener test not implemented");
        
        $this->queryHelper->getDefaultQueryListener(/* parameters */);
    }
}

