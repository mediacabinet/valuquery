<?php
namespace ValuQueryTest\DoctrineMongoOdm;

use ValuQuery\Selector\Selector;
use ValuQuery\DoctrineMongoOdm\QueryHelper;
use ValuQueryTest\TestAsset\Organ;

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
    protected function setUp()
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
    
    /**
     * @expectedException ValuQuery\DoctrineMongoOdm\Exception\InvalidQueryException
     */
    public function testInvalidQuery()
    {
        $this->queryHelper->query($this->createTestEntity('Cat',['name' => 'Tiger']));
    }
    
    public function testQueryById()
    {
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger']);
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        
        $result = $this->queryHelper->query((string) $tiger->id);
        $result->next();
        $this->assertSame($tiger, $result->current());
    }
    
    public function testQueryByIdUsingArrayNotation()
    {
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger']);
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
    
        $result = $this->queryHelper->query(['id' => (string)$tiger->id]);
        $result->next();
        $this->assertSame($tiger, $result->current());
    }
    
    /**
     * @expectedException ValuQuery\DoctrineMongoOdm\Exception\UnknownElementException
     */
    public function testQueryDoesntMatchByIdWhenIdDetectionIsDisabled()
    {
        $this->queryHelper->disableIdDetection();
        $this->queryHelper->query('59435d0b854a31bbad8da7aadf8ed385');
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
    
    public function testQueryByArray()
    {
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger', 'classes' => ['ultrafast']]);
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        $leopard = $this->createTestEntity('Cat',['name' => 'Leopard', 'classes' => ['ultrafast']]);
        
        $result = $this->queryHelper->query(
            ['classes' => ['$in' => ['ultrafast']]]);
        
        $this->assertEquals(2, $result->count());
        
        $result->next();
        $this->assertSame($tiger, $result->current());
        
        $result->next();
        $this->assertSame($leopard, $result->current());
    }
    
    public function testQueryByArrayUsingCursorParametersAndSingleField()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion', 'maxAge' => 10]);
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger', 'maxAge' => 15]);
        $dog = $this->createTestEntity('Dog',['name' => 'Bulldog', 'maxAge' => 17]);
    
        $result = $this->queryHelper->query(
            ['@sort' => ['name' => true], '@limit' => 2, '@offset' => 1],
            'name'
        );
    
        $this->assertEquals(['Lion', 'Tiger'], $result);
    }
    
    public function testQueryByArrayUsingCursorParametersAndMultipleFields()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion', 'maxAge' => 10]);
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger', 'maxAge' => 15]);
        $dog = $this->createTestEntity('Dog',['name' => 'Bulldog', 'maxAge' => 17]);
    
        $result = $this->queryHelper->query(
                ['@sort' => ['name' => true], '@limit' => 2, '@offset' => 1],
                ['name' => true, 'maxAge' => true]
        );
    
        $this->assertEquals([['name' => 'Lion', 'maxAge' => 10], ['name' => 'Tiger', 'maxAge' => 15]], $result);
    }
    
    public function testQueryByArrayUsingCursorParametersWithoutFields()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion', 'maxAge' => 10]);
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger', 'maxAge' => 15]);
        $dog = $this->createTestEntity('Dog',['name' => 'Bulldog', 'maxAge' => 17]);
    
        $result = $this->queryHelper->query(
                ['@sort' => ['name' => true], '@limit' => 2, '@offset' => 0]
        );
        
        $result->next();
        $this->assertSame($dog, $result->current());
        
        $result->next();
        $this->assertSame($lion, $result->current());
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
        
        $result = $this->queryHelper->query('[maxAge>=15]', ['name' => true, 'maxAge' => true]);
        $this->assertEquals([['name' => 'Lion', 'maxAge' => 15],['name' => 'Tomcat', 'maxAge' => 20]], $result);
    }
    
    public function testQueryManyUsingInvalidFieldsSyntax()
    {
        $tomcat = $this->createTestEntity('Cat',['name' => 'Tomcat', 'maxAge' => 20]);
        $result = $this->queryHelper->query('*', ['name', 'maxAge']);
        $this->assertInternalType('array', $result);
        $this->assertEquals(1, sizeof($result));
    }
    
    public function testQueryEmbeddedFields()
    {
        $organ = new Organ();
        $organ->name = 'Head';
        $organ->replacable = false;
        
        $lion = $this->createTestEntity('Cat',['name' => 'Lion', 'head' => $organ]);
        
        $result = $this->queryHelper->query($lion->id, ['head.name' => true, 'head.replacable' => true]);
        $this->assertEquals([['head' => ['name' => 'Head', 'replacable' => false]]], $result);
    }
    
    public function testQueryUsingOr()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger']);
        $dog = $this->createTestEntity('Dog',['name' => 'Bulldog']);
        
        $result = $this->queryHelper->query(['[name="Lion"]', $tiger->id]);
        $this->assertEquals(2, $result->count());
    }
    
    public function testQueryUsingOrAndCursorParameters()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion', 'maxAge' => 10]);
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger', 'maxAge' => 15]);
        $dog = $this->createTestEntity('Dog',['name' => 'Bulldog', 'maxAge' => 17]);
        
        $result = $this->queryHelper->query(
            ['[type="cat"]:sort(name ASC):limit(2)', 
            '#'.$dog->id.':sort(name DESC):limit(1):offset(1)'],
            'name'
        );
        
        $this->assertEquals(['Lion', 'Tiger'], $result);
    }
    
    public function testQueryWithEmbeddedField()
    {
        $head1 = new Organ();
        $head1->name = 'Head';
        $head1->replacable = false;
        
        $head2 = new Organ();
        $head2->name = 'Large head';
        $head2->replacable = false;
    
        $this->createTestEntity('Cat',['head' => $head1]);
        $this->createTestEntity('Cat',['head' => $head2]);
    
        $result = $this->queryHelper->query('*', ['head.name' => true]);
    
        $this->assertEquals([
            ['head' => ['name' => 'Head']],
            ['head' => ['name' => 'Large head']]
            ], 
            $result);
        
        $result = $this->queryHelper->query('*', 'head.name');
        
        $this->assertEquals([
            'Head',
            'Large head'
            ],
            $result);
    }
    
    public function testQueryAssociation()
    {
        $basicCat = $this->createTestEntity('Cat',['name' => 'Cat']);
        $superCat = $this->createTestEntity('Cat',['root' => $basicCat, 'name' => 'Supercat']);
        
        $result = $this->queryHelper->query('*', 'root');
        
        $this->assertEquals([$basicCat->id], $result);
    }
    
    public function testQueryWithSequences()
    {
        $basicCat = $this->createTestEntity('Cat',['name' => 'Cat']);
        $maxiCat = $this->createTestEntity('Cat',['parent' => $basicCat, 'name' => 'Maxicat']);
        $superCat1 = $this->createTestEntity('Cat',['parent' => $maxiCat, 'name' => 'Supercat']);
        $superCat2 = $this->createTestEntity('Cat',['name' => 'Supercat']);
        
        $helper = $this->queryHelper;
        
        $this->queryHelper->getQueryBuilder()->getEventManager()->attach('combineSequence', function($e) use($helper) {
            $query      = $e->getQuery();
            $sequence   = $e->getSequence();
            $combinator = $sequence->getChildCombinator();
            $queryBuilder = $e->getTarget();
        
            if ($combinator === Selector::COMBINATOR_CHILD) {
                $id = $helper->queryOne($query['query'], 'id');
                $query['query'] = ['parent' => $id];
                return true;
            }
            
            return false;
        });
        
        $result = $this->queryHelper->query('[name="Cat"]>[name="Maxicat"]>[name="Supercat"]', 'id');
        $this->assertEquals([$superCat1->id], $result);
    }
    
    public function testQueryWithEmptyQuery()
    {
        $this->assertEquals([], $this->queryHelper->query(''));
        $this->assertEquals([], $this->queryHelper->query([]));
    }
    
    public function testQueryWithPseudoSelectorFilter()
    {
        $this->dm->getConfiguration()->addFilter(
                'nonwaterbreedable',
                'ValuQueryTest\TestAsset\Filter\NonWaterBreedableFilter');
    
        $lion = $this->createTestEntity('Cat',['name' => 'Lion', 'isAbleToBreedUnderWater' => false]);
        $salmon = $this->createTestEntity('Fish',['name' => 'Salmon', 'isAbleToBreedUnderWater' => true]);
    
        $this->queryHelper->setFilters(['nonwaterbreedable']);
        $result = $this->queryHelper->query(':filter(nonwaterbreedable)', 'name');
        $this->assertEquals(['Lion'], $result);
    }
    
    public function testOrQueryFilterIsAppliedGlobally()
    {
        $this->dm->getConfiguration()->addFilter(
                'nonwaterbreedable',
                'ValuQueryTest\TestAsset\Filter\NonWaterBreedableFilter');
    
        $lion = $this->createTestEntity('Cat',['name' => 'Lion', 'isAbleToBreedUnderWater' => false]);
        $salmon = $this->createTestEntity('Fish',['name' => 'Salmon', 'isAbleToBreedUnderWater' => true]);
    
        $this->queryHelper->setFilters(['nonwaterbreedable']);
        $result = $this->queryHelper->query(['[name=Lion]:filter(nonwaterbreedable)', '[name=Salmon]'], 'name');
        $this->assertEquals(['Lion'], $result);
    }
    
    public function testPreviouslyEnabledFilterIsNotAppliedToNewQuery()
    {
        $this->dm->getConfiguration()->addFilter(
                'nonwaterbreedable',
                'ValuQueryTest\TestAsset\Filter\NonWaterBreedableFilter');
        
        $lion = $this->createTestEntity('Cat',['name' => 'Lion', 'isAbleToBreedUnderWater' => false]);
        $salmon = $this->createTestEntity('Fish',['name' => 'Salmon', 'isAbleToBreedUnderWater' => true]);
        
        $this->queryHelper->setFilters(['nonwaterbreedable']);
        $this->queryHelper->query(':filter(nonwaterbreedable)', 'name');
        
        $result = $this->queryHelper->query('*', 'name');
        $this->assertEquals(['Lion', 'Salmon'], $result);
    }
    
    public function testQueryOne()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        $this->assertSame($lion, $this->queryHelper->queryOne($lion->id));
    }
    
    public function testQueryOneWithIdSelector()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        $this->assertSame($lion, $this->queryHelper->queryOne('#'.$lion->id));
    }
    
    public function testQueryOneWithOneField()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        $this->assertEquals('Lion', $this->queryHelper->queryOne($lion->id, 'name'));
    }
    
    public function testQueryOneWithIdField()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        $this->assertEquals($lion->id, $this->queryHelper->queryOne($lion->id, 'id'));
    }
    
    public function testQueryOneWithMultipleFields()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion', 'canFly' => false]);
        $result = $this->queryHelper->queryOne($lion->id, ['name' => true, 'canFly' => true]);
        
        $this->assertEquals(
            ['name' => 'Lion', 'canFly' => false], 
            $result);
    }
    
    public function testQueryOneUsingInvalidFieldsSyntax()
    {
        $tomcat = $this->createTestEntity('Cat',['name' => 'Tomcat', 'maxAge' => 20]);
        $result = $this->queryHelper->queryOne('*', ['name', 'maxAge']);
        $this->assertSame($tomcat, $result);
    }
    
    public function testQueryOneWithEmbeddedField()
    {
        $organ = new Organ();
        $organ->name = 'Head';
        $organ->replacable = false;
        
        $cat = $this->createTestEntity('Cat',['head' => $organ]);
        
        $result = $this->queryHelper->queryOne($cat->id, ['head.name' => true]);
        
        $this->assertEquals(['head' => ['name' => 'Head']], $result);
    }
    
    public function testQueryOneWithEmptyQuery()
    {
        $this->assertEquals(null, $this->queryHelper->queryOne(''));
        $this->assertEquals(null, $this->queryHelper->queryOne([]));
    }
    
    /**
     * Tests queryHelper->count()
     */
    public function testCount()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger']);
        $bulldog = $this->createTestEntity('Dog',['name' => 'Bulldog']);
        
        $this->assertEquals(2, $this->queryHelper->count('[type=cat]'));
    }
    
    public function testCountWithOffset()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger']);
        $bulldog = $this->createTestEntity('Dog',['name' => 'Bulldog']);
        $grazydog = $this->createTestEntity('Dog',['name' => 'Grazydog']);
        
        $this->assertEquals(2, $this->queryHelper->count(':offset(2)'));
    }
    
    public function testCountWithLimit()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        $tiger = $this->createTestEntity('Cat',['name' => 'Tiger']);
        $bulldog = $this->createTestEntity('Dog',['name' => 'Bulldog']);
        $grazydog = $this->createTestEntity('Dog',['name' => 'Grazydog']);
    
        $this->assertEquals(3, $this->queryHelper->count(':limit(3)'));
    }

    /**
     * Tests queryHelper->exists()
     */
    public function testExists()
    {
        $lion = $this->createTestEntity('Cat',['name' => 'Lion']);
        $this->assertTrue($this->queryHelper->exists($lion->id));
    }
    
    public function testNotExistsIfEntityDoesNotExist()
    {
        $this->assertFalse($this->queryHelper->exists(['type' => 'cat']));
    }

    /**
     * Tests queryHelper->isEmptyQueryParam()
     */
    public function testIsEmptyQueryParam()
    {
        $this->assertTrue($this->queryHelper->isEmptyQueryParam(null));
        $this->assertTrue($this->queryHelper->isEmptyQueryParam(""));
        $this->assertTrue($this->queryHelper->isEmptyQueryParam(" "));
        $this->assertTrue($this->queryHelper->isEmptyQueryParam([]));
    }
    
    public function testIsNotEmptyQueryParam()
    {
        $this->assertFalse($this->queryHelper->isEmptyQueryParam('*'));
        $this->assertFalse($this->queryHelper->isEmptyQueryParam('a'));
        $this->assertFalse($this->queryHelper->isEmptyQueryParam('0'));
        $this->assertFalse($this->queryHelper->isEmptyQueryParam(false));
        $this->assertFalse($this->queryHelper->isEmptyQueryParam(['a']));
    }

    public function testEnableMongoIdDetection()
    {
        $this->queryHelper->enableIdDetection(QueryHelper::ID_MONGO);

        $selector = '112233445566778811223344';
        $query = $this->queryHelper->parse($selector);
        $this->assertEquals(['_id' => $selector], $query['query']);
    }
    
    public function testEnableUuid3Detection()
    {
        $this->queryHelper->enableIdDetection(QueryHelper::ID_UUID3);

        $selector = '11223344556677881122334412345678';
        $query = $this->queryHelper->parse($selector);
        $this->assertEquals(['_id' => $selector], $query['query']);
    }
    
    public function testEnableUuid5Detection()
    {
        $this->queryHelper->enableIdDetection(QueryHelper::ID_UUID5);

        $selector = '11223344556677881122334412345678';
        $query = $this->queryHelper->parse($selector);
        $this->assertEquals(['_id' => $selector], $query['query']);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEnableUnrecognizedIdDetection()
    {
        $this->queryHelper->enableIdDetection(0);
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

