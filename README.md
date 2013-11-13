# ValuQuery

[![Build Status](https://travis-ci.org/mediacabinet/valuquery.png?branch=master)](http://travis-ci.org/mediacabinet/valuquery)

ValuQuery is a library that provides CSS selector based query language support. ValuQuery can be used with various databases, including NoSQL and relative databases. Currently, MongoDB is best supported and there is also support for Doctrine's object-document mapper for MongoDB.

## Examples

### Querying MongoDB

Initializing query builder:
```php
$queryBuilder = new ValuQuery\QueryBuilder\QueryBuilder();
$listener = new ValuQuery\MongoDb\QueryListener();
$queryBuilder->getEventManager()->attach($listener);
```

Querying documents by ID:
```php
$query = $queryBuilder->query('#5281d29b4c16801609000191');
```
```php
['query' => ['_id' => MongoID('5281d29b4c16801609000191')]]
```

Querying documents by class:
```php
$query = $queryBuilder->query('.video');
```
```php
['query' => ['classes' => ['$in' => ['video']]]]
```

Querying documents using attribute selector:
```php
$query = $queryBuilder->query('[age>14]');
```
```php
['query' => ['age' => ['$gt' => 14]]]
```

## Query syntax

### ID selector

```css
#<ObjectId>
```
