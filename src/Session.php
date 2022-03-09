<?php

namespace Monolyth\Cesession;

use SessionHandlerInterface;

/**
 * Main Session interface. Should only be defined once per page load.
 */
class Session implements SessionHandlerInterface
{
    /** @var array */
    public static $session = [];

    /**
     * Array of handlers registered for this session.
     *
     * @var array
     */
    private $handlers = [];

    /**
     * Constructor.
     *
     * @param string $name The session_name() you want to use.
     * @return void
     */
    public function __construct(string $name)
    {
        session_name($name);
        session_set_save_handler($this, true);
    }

    /**
     * Register a session handler.
     *
     * @param Monolyth\Cesession\Handler $handler Handler object to use.
     * @param int $chainProbability The probability, expressed as a percentage,
     *  that calls on this handler will afterwards be forwarded to the next
     *  handler in the chain.
     * @return void
     * @see Cesession\Handler
     */
    public function registerHandler(Handler $handler, int $chainProbability = 0) : void
    {
        static $first = true;
        if ($first) {
            $this->handlers = [];
            $first = false;
        }
        $this->handlers[] = [$handler, $chainProbability];
    }

    /**
     * Internal method to walk the handler chain.
     *
     * @param string $method Method name to call on each handler. Note that the
     *  method definitions on handlers differ from those in
     *  SessionHandlerInterface.
     * @param int|null $highProbability Override to the handler's defined
     *  $chainProbability. Defaults to null, i.e. not used.
     * @param array $args The arguments to pass to $method.
     * @return mixed Whatever $method returned.
     */
    private function walk(string $method, int $highProbability = null, array $args = [])
    {
        $result = false;
        foreach ($this->handlers as $data) {
            list($handler, $chainProbability) = $data;
            $probability = $highProbability ?? $chainProbability;
            $result = call_user_func_array([$handler, $method], $args);
            if ($result and mt_rand(0, 100) > $probability) {
                return $result;
            }
        }
        return $result;
    }

    /**
     * Open a new session. Usually only makes sense if you need locking, which
     * is exactly what Cesession aims to prevent :)
     *
     * @param string $save_path Not used normally.
     * @param string $name Not used normally.
     * @return boolean True (we assume success).
     * @throws Monolyth\Cesession\NoHandlersDefinedException if no handlers were defined
     *  (which would make this whole module less than useful anyway).
     */
    public function open($save_path, $name) : bool
    {
        if (!$this->handlers) {
            throw new NoHandlersDefinedException;
        }
        foreach ($this->handlers as $handler) {
            if (method_exists($handler[0], 'open')) {
                $handler[0]->open($save_path, $name);
            }
        }
        return true;
    }

    /**
     * Closes the current session. Usually only makes sense if you need locking,
     * which is exactly what Cesession aims to prevent :)
     *
     * @return boolean True (we assume success).
     */
    public function close() : bool
    {
        foreach ($this->handlers as $handler) {
            if (method_exists($handler[0], 'close')) {
                $handler[0]->close();
            }
        }
        return true;
    }

    /**
     * Read the requested session.
     *
     * @param string $id The session ID.
     * @return string The read data.
     */
    public function read($id) : string
    {
        if ($session = $this->walk('read', null, [$id])) {
            self::$session = $session;
            return self::$session['data'];
        }
        return '';
    }

    /**
     * Write the requested session.
     *
     * @param string $id The session ID.
     * @param string $data The serialized session data as passed by PHP.
     * @return boolean True on success, else false.
     */
    public function write($id, $data) : bool
    {
        return (bool)$this->walk(
            'write',
            null,
            [$id, compact('data') + self::$session]
        );
    }

    /**
     * Destroy the requested session.
     *
     * @param string $id The session ID.
     * @return boolean True on success, else false.
     */
    public function destroy($id) : bool
    {
        // Override with 100 to close every handler.
        return (bool)$this->walk('destroy', 100, [$id]);
    }

    /**
     * Run garbage collection.
     *
     * @param integer $maxlifetime The number of seconds stale session data must
     *  be older than to be eligible for garbage collection.
     * @return boolean True on success, else false.
     */     
    #[\ReturnTypeWillChange]
    public function gc($maxlifetime) : bool
    {
        return $this->walk('gc', null, [$maxlifetime]);
    }

    /**
     * Force a method call on all handlers. Note that method signature might be
     * slightly different than in SessionHandlerInterface.
     *
     * @param string $method The method to force (e.g. `write`).
     * @param array $args Arguments to pass.
     * @return mixed Whatever $method returned.
     */
    public function force(string $method, array $args = [])
    {
        return $this->walk($method, 100, $args);
    }
}

