<?php
namespace ValuQueryTest\DoctrineMongoOdm\Path;

use ValuQueryTest\DoctrineMongoOdm\AbstractTestCase;
use ValuQuery\DoctrineMongoOdm\Path\Resolver;
use Doctrine\ODM\MongoDB\DocumentManager;
use ValuQuery\DoctrineMongoOdm\ValueConverter;
use ValuQuery\Selector\SimpleSelector\Path;
use ValuQueryTest\TestAsset\Dog;
use ValuQueryTest\TestAsset\Category;
use ValuQuery\Selector\SimpleSelector\Id;
use ValuQuery\Selector\SimpleSelector\Role;

class ResolverTest extends AbstractTestCase
{

    private $resolver;
    
    protected function setUp()
    {
        parent::setUp();
        $this->resolver = new Resolver($this->dm, 'ValuQueryTest\TestAsset\Animal'); 
    }
    
    public function testSetGetDocumentManager()
    {
        $dm = DocumentManager::create($this->dm->getConnection(), $this->dm->getConfiguration(), $this->dm->getEventManager());
        $this->resolver->setDocumentManager($dm);
        $this->assertSame($dm, $this->resolver->getDocumentManager());
    }
    
    public function testSetGetDocumentName()
    {
        $this->assertEquals('ValuQueryTest\TestAsset\Animal', $this->resolver->getDocumentName());
        $this->resolver->setDocumentName('ValuQueryTest\TestAsset\Cat');
        $this->assertEquals('ValuQueryTest\TestAsset\Cat', $this->resolver->getDocumentName());
    }
    
    public function testSetGetPathField()
    {
        $this->assertEquals('path', $this->resolver->getPathField());
        $this->resolver->setPathField('route');
        $this->assertEquals('route', $this->resolver->getPathField());
    }
    
    public function testSetGetRoleField()
    {
        $this->assertEquals('roles', $this->resolver->getRoleField());
        $this->resolver->setRoleField('name');
        $this->assertEquals('name', $this->resolver->getRoleField());
    }
    
    public function testSetGetValueConverter()
    {
        $this->assertInstanceOf('ValuQuery\DoctrineMongoOdm\ValueConverter', $this->resolver->getValueConverter());
        $this->assertSame($this->dm, $this->resolver->getValueConverter()->getDocumentManager());
        $converter = new ValueConverter($this->dm);
        $this->resolver->setValueConverter($converter);
        $this->assertSame($converter, $this->resolver->getValueConverter());
    }
    
    public function testResolve()
    {
        $path = new Path(['Chordata', 'Mammalia', 'Carnivora', 'Felidae', 'Felis', 'F. catus']);
        $this->assertEquals('/Chordata/Mammalia/Carnivora/Felidae/Felis/F. catus', $this->resolver->resolve($path));
    }
    
    public function testResolveWithWildcard()
    {
        $path = new Path(['Chordata', 'Mammalia', '*']);
        $this->assertEquals('/Chordata/Mammalia/*', $this->resolver->resolve($path));
    }
    
    public function testResolveWithEmptyPathSelector()
    {
        $path = new Path([]);
        $this->assertEquals('/', $this->resolver->resolve($path));
    }
    
    public function testResolveWithIdSubSelector()
    {
        $this->resolver->setDocumentName('ValuQueryTest\TestAsset\Category');
        
        $category = new Category();
        $category->path = '/Dogs';
        
        $this->dm->persist($category);
        $this->dm->flush();
        
        $path = new Path([new Id($category->id)]);
        $this->assertEquals('/Dogs', $this->resolver->resolve($path));
    }
    
    public function testResolveWithRoleSubSelector()
    {
        $this->resolver->setDocumentName('ValuQueryTest\TestAsset\Dog');
        
        $dog = new Dog();
        $dog->path = '/Shephards';
        $dog->roles = ['shephards'];
        
        $this->dm->persist($dog);
        $this->dm->flush();
        
        $path = new Path([new Role('shephards')]);
        $this->assertEquals('/Shephards', $this->resolver->resolve($path));
    }
    
    /**
     * @expectedException ValuQuery\DoctrineMongoOdm\Path\Exception\IllegalPathSelectorException
     */
    public function testResolveWithIllegalSubSelector()
    {
        $path = new Path([new Path([])]);
        $this->resolver->resolve($path);
    }
    
    public function testResolveWithNonMatchingSubSelector()
    {
        $path = new Path([new Role('x')]);
        $this->assertFalse($this->resolver->resolve($path));
    }
}