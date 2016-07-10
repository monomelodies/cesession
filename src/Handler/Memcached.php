<?php

namespace Monolyth\Cesession\Handler;

use Monolyth\Cesession\Handler;

class Memcached implements Handler
{
    private $mc;
    private $exists = false;

    public function __construct(\Memcached $mc)
    {
        $this->mc = $mc;
    }

    private function getKey()
    {
        return sprintf('%s/session/%s', session_name(), session_id());
    }
    
    public function read($id)
    {
        if ($data = $this->mc->get($this->getKey())
            and $data = json_decode($data, true)
        ) {
            $this->exists = true;
            return $data;
        }
        $this->exists = false;
        return false;
    }
    
    public function write($id, $data)
    {
        $values = $data + compact('id'); // Default.
        $values['dateactive'] = date('Y-m-d H:i:s');
        return $this->mc->set(
            $this->getKey(),
            serialize($data),
            ini_get('session.gc_maxlifetime')
        );
    }
    
    public function destroy($id)
    {
        return $this->mc->delete($this->getKey());
    }
    
    public function gc($maxlifetime)
    {
        // Handled automatically be Memcached (3rd parameter to set).
        return true;
    }
}

