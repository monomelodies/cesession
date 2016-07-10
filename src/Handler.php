<?php

namespace Monolyth\Cesession;

/**
 * Interface for session handlers.
 */
interface Handler
{
    public function read($id);
    public function write($id, $data);
    public function destroy($id);
    public function gc($maxlifetime);
}

