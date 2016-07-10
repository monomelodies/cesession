<?php

namespace Monolyth\Cesession\Tests;

use Monolyth\Cesession\Session;
use Monolyth\Cesession\Handler;
use PDO;
use Memcached;

class SessionTest
{
    static private $pdo = null;
    static private $sessions = null;
    private $conn = null;

    public function __wakeup()
    {
        if (self::$pdo === null) {
            self::$pdo = new PDO('sqlite::memory:');
            self::$pdo->exec(file_get_contents(dirname(__DIR__).'/info/sql/sqlite.sql'));
            self::$sessions = self::$pdo->prepare("SELECT * FROM cesession_session");
            ini_set('session.serialize_handler', 'php_serialize');
        }
        return [];
    }

    /**
     * On an empty session table, starting a session should insert exactly one
     * row {?}. After reopening, a session value should be persisted {?}.
     */
    public function testSession()
    {
        $session = new Session('testing');
        $session->registerHandler(new Handler\Pdo(self::$pdo));
        $session->open('', 'testing');
        $session->read('testing');
        $_SESSION['foo'] = 'bar';
        $session->write('testing', serialize($_SESSION));
        $session->close();
        self::$sessions->execute();
        $cnt = self::$sessions->fetchAll();
        yield assert(count($cnt) == 1);
        $_SESSION = [];
        $session->open('', 'testing');
        $_SESSION = unserialize($session->read('testing'));
        yield assert($_SESSION['foo'] == 'bar');
        $session->close();
    }

    /**
     * Using memcache, a session should also be "persisted" {?}.
     */
    public function testMemcached()
    {
        $session = new Session('testing');
        $memcached = new Memcached;
        $memcached->addServer('127.0.0.1', 11211);
        $memcached->flush();
        $session->registerHandler(new Handler\Memcached($memcached));
        $session->open('', 'testing');
        $session->read('testing');
        $_SESSION['foo'] = 'bar';
        $session->write('testing', serialize($_SESSION));
        $session->close();
        self::$sessions->execute();
        $_SESSION = [];
        $session->open('', 'testing');
        $_SESSION = unserialize($session->read('testing'));
        yield assert($_SESSION['foo'] == 'bar');
        $session->close();
    }

    /**
     * Using multiple handlers, a session value should be persisted {?}. When
     * forcing a write, it should be stored throuh the database fallback handler
     * {?}.
     */
    public function testHandlers()
    {
        $session = new Session('testing');
        $memcached = new Memcached;
        $memcached->addServer('127.0.0.1', 11211);
        $memcached->flush();
        $session->registerHandler(new Handler\Memcached($memcached), 10);
        $session->registerHandler(new Handler\Pdo(self::$pdo));
        $session->open('', 'testing');
        $session->read('testing');
        $_SESSION['foo'] = 'bar';
        $session->write('testing', serialize($_SESSION));
        $session->close();
        self::$sessions->execute();
        $cnt = self::$sessions->fetchAll();
        yield assert(count($cnt) == 1);
        $_SESSION = [];
        $session->open('', 'testing');
        $_SESSION = unserialize($session->read('testing'));
        yield assert($_SESSION['foo'] == 'bar');
        $session->force(
            'write',
            [
                'testing',
                ['data' => serialize($_SESSION)]
            ]
        );
        self::$sessions->execute();
        $cnt = self::$sessions->fetchAll();
        yield assert(count($cnt) == 1);
        session_write_close();
    }
}

