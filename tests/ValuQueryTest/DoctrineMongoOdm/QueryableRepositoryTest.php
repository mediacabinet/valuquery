<?php
namespace ValuQueryTest\DoctrineMongoOdm;

use ValuQuery\DoctrineMongoOdm\QueryableRepository;

class QueryableRepositoryTest extends AbstractTestCase
{

    /**
     * @var QueryableRepository
     */
    private $repository;

    /**
     * Prepares the environment before running a test.
     */
    public function setUp()
    {
        parent::setUp();
        
        $this->repository = $this->dm->getRepository('ValuQueryTest\TestAsset\Animal');
    }
    
    public function testQueryHelperIsInitializedCorrectly()
    {
        $this->assertSame($this->dm, $this->repository->getQueryHelper()->getDocumentManager());
        $this->assertEquals('ValuQueryTest\TestAsset\Animal', $this->repository->getQueryHelper()->getDocumentName());
    }
    
    public function testQuery()
    {
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger']);
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        
        $result = $this->repository->query(['name' => 'Tiger']);
        $this->assertEquals(1, $result->count());
        $result->next();
        
        $this->assertSame($tiger, $result->current());
    }
    
    public function testQueryOne()
    {
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger']);
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        
        $result = $this->repository->queryOne(['name' => 'Lion']);
        $this->assertSame($lion, $result);
    }
    
    public function testCount()
    {
        $tiger1 = $this->createTestEntity('Cat',['name' => 'Tiger']);
        $tiger2 = $this->createTestEntity('Cat',['name' => 'Tiger']);
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        
        $this->assertEquals(2, $this->repository->count(['name' => 'Tiger']));
    }
    
    public function testExists()
    {
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger']);
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        
        $this->assertTrue($this->repository->exists(['name' => 'Tiger']));
    }
    
    public function testNotExists()
    {
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger']);
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        
        $this->assertFalse($this->repository->exists(['name' => 'Lionness']));
    }
    
    public function testQueryWithUuidStrategy()
    {
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger']);
        
        $result = $this->repository->query((string) $tiger->id);
        $result->next();
        
        $this->assertSame($tiger, $result->current());
    }
    
    public function testQueryWithMongoIdStrategy()
    {
        $cars = $this->createTestEntity('Category',['name' => 'Cars']);
        
        $repository = $this->dm->getRepository('ValuQueryTest\TestAsset\Category');
        $result = $repository->query((string) $cars->id);
        $result->next();
        
        $this->assertSame($cars, $result->current());
    }
}