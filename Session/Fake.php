<?php

/**
 * Fake session handler.
 *
 * Stuff like shell scripts don't need no stinking sessions.
 *
 * @package monolyth
 * @author Marijn Ophorst <marijn@monomelodies.nl>
 * @copyright MonoMelodies 2013, 2014
 * @see monolyth\core\Session_Model
 */

namespace monolyth;
use ErrorException;

class Fake_Session_Model extends core\Session_Model
{
    use core\Singleton;

    public function __construct()
    {
    }

    /**
     * "Constructor". Initialise a new or existing session.
     *
     * @return void
     */
    public function init()
    {
    }

    public function get($key)
    {
        return null;
    }

    public function set($key, $value = null)
    {
    }

    public function exists($key)
    {
        return false;
    }

    public function all()
    {
        return [];
    }

    protected function create()
    {
        return false;
    }

    public function write($id, $mode = 0)
    {
        return false;
    }

    public function reset()
    {
    }

    public function destroy($id, $random)
    {
        return 0;
    }

    public function gc($maxlifetime)
    {
        return 0;
    }

    public function flush($id = null)
    {
    }

    protected function getSuspectSessions()
    {
        return 0;
    }
}

