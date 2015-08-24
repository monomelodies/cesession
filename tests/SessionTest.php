<?php

use Cesession\Session;

class SessionTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $db = new PDO('sqlite::memory:');
        $session = new Session;
        $this->assertEquals(
            1,
            preg_match('@^[0-9a-zA-Z]{32}$@', $session->id())
        );
        return $session;
    }

    /**
     * @depends testCreate
     */
    public function testSessionStarts(Session $session)
    {
        session_start();
    }
}

