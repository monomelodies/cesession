<?php

use Monolyth\Cesession\Session;
use Monolyth\Cesession\Handler;
use Gentry\Gentry\Wrapper;

/** Tests for sessions */
return function () : Generator {
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec(file_get_contents(dirname(__DIR__).'/info/sqlite.sql'));
    $sessions = $pdo->prepare("SELECT * FROM cesession_session");
            //ini_set('session.serialize_handler', 'php_serialize');
    /**
     * On an empty session table, starting a session should insert exactly one
     * row. After reopening, a session value should be persisted.
     */
    yield function () use ($pdo, $sessions) {
        $session = Wrapper::createObject(Session::class, 'testing');
        $session->registerHandler(Wrapper::createObject(Handler\Pdo::class, $pdo));
        $session->open('', 'testing');
        $session->read('testing');
        $_SESSION['foo'] = 'bar';
        $session->write('testing', serialize($_SESSION));
        $session->close();
        $sessions->execute();
        $cnt = $sessions->fetchAll();
        assert(count($cnt) == 1);
        $_SESSION = [];
        $session->open('', 'testing');
        $_SESSION = unserialize($session->read('testing'));
        assert($_SESSION['foo'] == 'bar');
        $session->close();
    };

    /** Using memcache, a session should also be "persisted". */
    yield function () use ($sessions) {
        $session = Wrapper::createObject(Session::class, 'testing');
        $memcached = new Memcached;
        $memcached->addServer('127.0.0.1', 11211);
        $memcached->flush();
        $session->registerHandler(Wrapper::createObject(Handler\Memcached::class, $memcached));
        $session->open('', 'testing');
        $session->read('testing');
        $_SESSION['foo'] = 'bar';
        $session->write('testing', serialize($_SESSION));
        $session->close();
        $sessions->execute();
        $_SESSION = [];
        $session->open('', 'testing');
        $_SESSION = unserialize($session->read('testing'));
        assert($_SESSION['foo'] == 'bar');
        $session->close();
    };

    /**
     * Using multiple handlers, a session value should be persisted. When
     * forcing a write, it should be stored throuh the database fallback handler.
     */
    yield function () use ($pdo, $sessions) {
        $session = Wrapper::createObject(Session::class, 'testing');
        $memcached = new Memcached;
        $memcached->addServer('127.0.0.1', 11211);
        $memcached->flush();
        $session->registerHandler(Wrapper::createObject(Handler\Memcached::class, $memcached), 10);
        $session->registerHandler(Wrapper::createObject(Handler\Pdo::class, $pdo));
        $session->open('', 'testing');
        $session->read('testing');
        $_SESSION['foo'] = 'bar';
        $session->write('testing', serialize($_SESSION));
        $session->close();
        $sessions->execute();
        $cnt = $sessions->fetchAll();
        assert(count($cnt) == 1);
        $_SESSION = [];
        $session->open('', 'testing');
        $_SESSION = unserialize($session->read('testing'));
        assert($_SESSION['foo'] == 'bar');
        $session->force(
            'write',
            [
                'testing',
                ['data' => serialize($_SESSION)]
            ]
        );
        $sessions->execute();
        $cnt = $sessions->fetchAll();
        assert(count($cnt) == 1);
        session_write_close();
    };
};

