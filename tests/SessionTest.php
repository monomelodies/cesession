<?php

use Cesession\Session;
use Cesession\Handler;

class SessionTest extends PHPUnit_Extensions_Database_TestCase
{
    static private $pdo = null;
    private $conn = null;

    protected function getSetUpOperation()
    {
        // Needed for truncates to cascade...
        return PHPUnit_Extensions_Database_Operation_Factory::CLEAN_INSERT(true);
    }
    
    public function getConnection()
    {
        if ($this->conn === null) {
            $db = get_current_user().'_ft';
            if (self::$pdo === null) {
                self::$pdo = new PDO('sqlite::memory:');
                self::$pdo->exec(file_get_contents(dirname(__DIR__).'/info/sql/sqlite.sql'));
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo, $db);
        }
        return $this->conn;
    }
    
    public function getDataSet()
    {
        return $this->createXmlDataSet(__DIR__.'/data.xml');
    }

    public function testSession()
    {
        $session = new Session('phpunit');
        $session->registerHandler(new Handler\Pdo(self::$pdo));
        @session_start();
        $_SESSION['foo'] = 'bar';
        session_write_close();
        $cnt = self::$pdo->query("SELECT * FROM cesession_session");
        $this->assertEquals(1, count($cnt));
        $_SESSION = [];
        @session_start();
        $this->assertEquals('bar', $_SESSION['foo']);
        session_write_close();
    }

    public function testHandlers()
    {
        $session = new Session('phpunit');
        $memcached = new Memcached;
        $memcached->addServer('http://localhost', 11211);
        $session->registerHandler(new Handler\Memcached($memcached), 10);
        $session->registerHandler(new Handler\Pdo(self::$pdo));
        @session_start();
        $_SESSION['foo'] = 'bar';
        session_write_close();
        $_SESSION = [];
        @session_start();
        $this->assertEquals('bar', $_SESSION['foo']);
        $session->force(
            'write',
            [
                session_id(),
                ['data' => serialize($_SESSION)] + $session::$session,
            ]
        );
        $cnt = self::$pdo->query("SELECT * FROM cesession_session");
        $this->assertEquals(1, count($cnt));
        session_write_close();
    }
}

