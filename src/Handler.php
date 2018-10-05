<?php

namespace Monolyth\Cesession;

/**
 * Interface for session handlers.
 */
interface Handler
{
    public function read(string $id) :? array;
    public function write(string $id, array $data) : bool;
    public function destroy(string $id) : bool;
    public function gc(int $maxlifetime) : bool;
}

