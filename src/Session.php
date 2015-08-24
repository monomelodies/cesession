<?php

namespace Cesession;

class Session implements Handler
{
    public static $session;
    private $handlers = [];

    public function __construct($name)
    {
        session_start($name);
        session_set_save_handler(
            [$session, 'open'],
            [$session, 'close'],
            [$session, 'read'],
            [$session, 'write'],
            [$session, 'destroy'],
            [$session, 'gc']
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
        self::$session = $this->walk('open');
    }

    public function close()
    {
        return $this->walk('close');
    }

    public function read($id)
    {
        return $this->walk('read', null, [$id]);
    }

    public function write($id, $data)
    {
        return $this->walk('write', null, [$id, $data]);
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

