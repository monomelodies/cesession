# CeSession
Alternative PHP session handler

## Who is this for?
While PHP supports session handling, the implementation is less than stellar.
Cesession provides an alternative with built-in helpers to use either an SQL
database, memcached or a NoSQL database.

Cesession offers a seamless interface with the well-known `$_SESSION`
superglobal, so any existing code should Just Work(tm).

An added advantage of using SQL based databases is that you can set up a
foreign key constraint between the current user and the current session, so
people get automatically logged out.

## Installation

### Composer (recommended)
`$ composer require monomelodies/cesession`

### Manual
1. Clone or download the repository;
2. Add the `Cesession` namespace to your autoloader for
   `/path/to/cesession/src`.
3. Create the relevant table (see scripts in `./info/sql`)

That's it!

## Setting up
To begin, _before_ any call to `session_start` create the `Cesession\Session`
object and register a _handler_. Currently Cesession ships with a `Pdo` handler
that does exactly what its name implies (store the session data in a
PDO-compatible database, e.g. PostgreSQL or MySQL):

```php
<?php

use Cesession\Session;
use Cesession\Handler;

$session = new Session('my-session-name');
$db = new PDO('dsn', 'user', 'pass');
$handler = new Handler\Pdo($db);
$session->registerHandler($handler);
session_start();
```

## Registering a PDO handler
To register a handler, simply call the `registerHandler` method on the
`$session` object and pass in a handler object. Each handler object _must_
implement the `Cesession\Handler` interface. This is done both for type hinting
and to ensure the required methods exist. The `Handler` interface is a subset
of PHP's built-in `SessionHandlerInterface`.

The optional second argument to `registerHandler` is a probability percentage
between 0 and 100. This signifies the probability that for supporting calls, the
action is _also_ delegated to the next handler (if defined).

E.g., say you want to store sessions in Memcached (fast!) but persist to a PDO
backend every ten calls on average:

```php
<?php

$session->registerHandler(new Handler\Memcached($memcached), 10);
$session->registerHandler(new Handler\Pdo($db));
```

> Note: currently only the Pdo handler is supported out of the box.

