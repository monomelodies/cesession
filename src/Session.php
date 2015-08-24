<?php

namespace Cesession;

class Session implements Handler
{
    public static $session = [];
    private $handlers = [];

    public function __construct($name)
    {
        session_name($name);
        session_set_save_handler(
            [$this, 'open'],
            [$this, 'close'],
            [$this, 'read'],
            [$this, 'write'],
            [$this, 'destroy'],
            [$this, 'gc']
        );
    }

    public function registerHandler(Handler $handler, $chainProbability = 0)
    {
        static $first = true;
        if ($first) {
            $this->handlers = [];
            $first = false;
        }
        $this->handlers[] = [$handler, $chainProbability];
    }

    private function walk($method, $highProbability = null, array $args = [])
    {
        foreach ($this->handlers as $data) {
            list($handler, $chainProbability) = $data;
            $probability = isset($highProbability) ?
                $highProbability :
                $chainProbability;
            $result = call_user_func_array([$handler, $method], $args);
            if (mt_rand(0, 100) > $probability) {
                return $result;
            }
        }
        return $result;
    }

    public function open()
    {
        $this->walk('gc');
    }

    public function close()
    {
        return $this->walk('gc');
    }

    public function read($id)
    {
        if ($session = $this->walk('read', null, [$id])) {
            self::$session = $session;
        }
        return self::$session['data'];
    }

    public function write($id, $data)
    {
        return $this->walk(
            'write',
            null,
            [$id, self::$session + compact('data')]
        );
    }

    public function destroy($id)
    {
        // Override with 100 to close every handler.
        return $this->walk('destroy', 100, [$id]);
    }

    public function gc()
    {
        return $this->walk('gc');
    }
}

