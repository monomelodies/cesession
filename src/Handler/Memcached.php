<?php

namespace Monolyth\Cesession\Handler;

use Monolyth\Cesession\Handler;

class Memcached implements Handler
{
    /** @var Memcached */
    private $mc;
    /** @var bool */
    private $exists = false;

    /**
     * @param Memcached $mc
     * @return void
     */
    public function __construct(\Memcached $mc)
    {
        $this->mc = $mc;
    }

    /**
     * Helper to get a formatted key for memcached based on a session ID.
     *
     * @param string $id Session ID, defaults to `session_id()`.
     * @return string
     */
    private function getKey(string $id = null) : string
    {
        $id = $id ?: session_id();
        return sprintf('%s/session/%s', session_name(), $id);
    }
    
    /**
     * @param string $id
     * @return array|null
     */
    public function read(string $id) :? array
    {
        if ($data = $this->mc->get($this->getKey($id))
            and $data = json_decode($data, true)
        ) {
            return $data;
        }
        $this->exists = false;
        return null;
    }
    
    /**
     * @param string $id
     * @param array $data
     * @return bool
     */
    public function write(string $id, array $data) : bool
    {
        $values = $data + compact('id'); // Default.
        $values['dateactive'] = date('Y-m-d H:i:s');
        return $this->mc->set(
            $this->getKey($id),
            json_encode($values),
            ini_get('session.gc_maxlifetime')
        );
    }
    
    /**
     * @param string $id
     * @return bool
     */
    public function destroy(string $id) : bool
    {
        return $this->mc->delete($this->getKey());
    }
    
    /**
     * Garabage collection is done by memcached internally, so this is
     * actually just a stub.
     *
     * @param int $maxlifetime
     * @return bool Always returns true.
     */
    public function gc(int $maxlifetime) : bool
    {
        // Handled automatically by Memcached.
        return true;
    }
}

