# CeSession
Alternative PHP session handler

Who is this for?
----------------

While PHP supports session handling, the implementation is less than stellar.
Cesession provides an alternative with built-in helpers to use either an SQL
database, memcached or a NoSQL database.

Cesession offers a seamless interface with the well-known `$_SESSION`
superglobal, so any existing code should Just Work(tm).

An added advantage of using SQL based databases is that you can set up a
foreign key constraint between the current user and the current session, so
people get automatically logged out.

Installation
------------

1. Clone the repository;
2. Optionally, when using stand-alone, include the `bootstrap.php` file.
3. Set up your desired sessions and optionally create tables for them.

That's it!

Setting up
----------

At its core Cesession registers handlers for reading and writing PHP sessions
via its own methods. You set it up by creating a session object and injecting
the various objects you want to use for handling:

    <?php

    use cesession\Session;
    use cesession\Session\Memcache;
    use cesession\Session\MySQL;

    $session = new Session;
    $session->registerHandler(new Memache([...config....]);
    $session->registerHandler(new MySQL(new PDO(...connection...)));
    $session->start();

The handlers are used in order of registration, so in this example Cesession
would first try to use Memcache. If unavailable or otherwise failed, it tries
MySQL next, etc.

You may write your own implementations of handlers. These must implement the
`cesession\Handler` interface.

Failure to correctly handle a request must throw a `cesession\HandlerException`
in order to notify the Session.

Delegation
----------

Sometimes you want a handler to delegate to the next handler, even if it was
succesful. A prominent example of this is the Memcache handler, which
periodically forces the next handler to be called on write (since Memcache is
volatile and presumably the next handler, like MySQL, is more persistent).

To make a method in a handler delegate, throw a `cesession\DelegateException`
when you want to relinquish control to a "super-handler".

If no handlers are left to delegate to, Cesession discards the final throw.

Plugins
-------

Cesession supports plugins, like for the above mentioned user login. Since we
don't want to force a user system on you, the implementation is left to the
programmer. It's simple enough though: a Cesession session has events which can
be triggered and listened to.

To listen:

    <?php

    // ...assuming $session is the cesession\Session...
    $session->on('write', function() {
        // ...do what you want to do...
    });

To trigger:

    <?php

    $session->trigger('write');

You can also autotrigger:

    <?php

    $session->on('user.login', function() use($session) {
        $session->trigger('write');
    });

You should consider implementing a singleton wrapper or using dependency
injection (for instance with the Disclosure framework) to ensure your code
uses the same Session object throughout. `$_SESSION` is a superglobal, but
Cesession isn't!

Plugins are called after the associated action was deemed succesful, i.e. at
least one handler did not throw a `HandlerException`. You can also register
pre-handlers which will be executed before any handling is attempted. They work
in the same manner, only use the `before` method:

    <?php

    $session->before('write', function() {
        // ...e.g., unset something we _never_ want in our sessions...
    });

###Predefined events###

Cesession triggers a number of predefined events you can listen for:
`read`, `write`, `stop`. These are triggered at the obvious moments.

###Order of declaration###

Any event you want to act on _must_ be defined using either `on` or `before`
_before_ the actual event happens. You are of course free to define events
anywhere in your code, but "what's done is done". Cesession by design does not
keep a history of events to implement something promise-like.
