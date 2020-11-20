<?php

namespace ValuQueryTest\DoctrineMongoOdm;

use PHPUnit_Framework_TestCase as TestCase;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\Common\EventManager as Evm;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;

abstract class AbstractTestCase extends TestCase
{
    /**
     * DocumentManager
     * @var DocumentManager
     */
    protected $dm;

    protected function setUp()
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

        $connection = new Connection('mongodb://' . getenv('MONGO_SERVER') . ':' . getenv('MONGO_PORT'), [],
            $configuration, $evm);

        $this->dm = DocumentManager::create($connection, $configuration, $evm);

        $this->dm->getConnection()->dropDatabase('valuquerytest');
    }

    /**
     * Create a persisted test entity
     *
     * @param string $type
     * @param array $specs
     * @return \Doctrine\ODM\MongoDB\DocumentManager
     */
    protected function createTestEntity($type, array $specs)
    {
        $class = 'ValuQueryTest\\TestAsset\\' . ucfirst($type);
        $entity = new $class;

        foreach ($specs as $key => $value) {
            $entity->{$key} = $value;
        }

        $this->dm->persist($entity);
        $this->dm->flush();

        return $entity;
    }
}