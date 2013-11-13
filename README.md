# ValuQuery

[![Build Status](https://travis-ci.org/mediacabinet/valuquery.png?branch=master)](http://travis-ci.org/mediacabinet/valuquery)

ValuQuery is a library that provides CSS selector based query language support. ValuQuery can be used with various databases, including NoSQL and relative databases. Currently, MongoDB is best supported and there is also support for Doctrine's object-document mapper for MongoDB.

## Core components

The core components of ValuQuery are `SelectorParser` and `QueryBuilder`. `SelectorParser` parses a `Selector` object from selector string. `QueryBuilder` iterates components of `Selector` object and passes each component to its listeners, which in turn parse the query properties.

There are pre-built listeners for MongoDB and a separate listener for Doctrine's MongoODM.

## QueryBuilder examples

### Querying MongoDB

**Initializing query builder:**
```php
$queryBuilder = new ValuQuery\QueryBuilder\QueryBuilder();
$listener = new ValuQuery\MongoDb\QueryListener();
$queryBuilder->getEventManager()->attach($listener);
```

**Querying documents by ID:**
```php
$query = $queryBuilder->query('#5281d29b4c16801609000191');
```
Contents of `$query`:
```php
['query' => ['_id' => new MongoID('5281d29b4c16801609000191')]]
```

**Querying documents by class:**
```php
$query = $queryBuilder->query('.video');
```
Contents of `$query`:
```php
['query' => ['classes' => ['$in' => ['video']]]]
```

**Find second document, where name begins with "John" in ascending order:**
```php
$query = $queryBuilder->query('[name^="John"]:sort(age asc):limit(1):skip(1)');
```
Contents of `$query`:
```php
[
  'query' => ['name' => new MongoRegex("/^John/")], 
  'sort' => ["age" => 1],
	'limit' => 1,
	'skip' => 1
]
```

## Using ValuQuery with Doctrine MongoDB ODM

It is easiest to use ValuQuery with ODM by integrating it to DocumentRepository by extending `ValuQuery\DoctrineMongoOdm\DocumentRepository`. The class provides convenient methods `query`, `queryOne`, `count` and `exists`. If you don't want to use DocumentRepository, you should use `QueryHelper`, which provides the same methods. Actually, DocumentRepository uses QueryHelper internally.

### Using DocumentRepository

Set up your custom repository by extending `ValuQuery\DoctrineMongoOdm\DocumentRepository`:
```php
use ValuQuery\DoctrineMongoOdm\DocumentRepository;

class FileRepository  extends DocumentRepository
{
//...
}
```

Now you can use your repository to perform CSS based queries and more!

Imagine, you want to fetch the titles of your hi-res videos:
```php
$titles = $fileRepository->query('.video.hires', 'title');
```
Contents of `$titles`:
```php
['Best football scene HD', 'Super Holiday HD', 'In HD: amazing nature']
```