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
                //self::$pdo = new PDO('sqlite::memory:');
                self::$pdo = new PDO('sqlite:bla');
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

    /**
     * @outputBuffering enabled
     */
    public function testSession()
    {
        $session = new Session('phpunit');
        $session->registerHandler(new Handler\Pdo(self::$pdo));
        @session_start();
        $_SESSION['foo'] = 'bar';
        session_write_close();
        foreach (self::$pdo->query("SELECT * FROM cesession_session") as $sess) {
            var_dump($sess);
        }
        @session_start();
        var_dump($_SESSION);
        var_dump(Session::$session);
    }
}

