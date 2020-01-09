<?php

namespace Monolyth\Cesession;

/**
 * Interface for session handlers.
 */
interface Handler
{
    /**
     * Read the session identified by `$id`.
     *
     * @param string $id
     * @return array|null Session data, or null.
     */
    public function read(string $id) :? array;

    /**
     * Write the data in `$data` to the session identified by `$id`.
     *
     * @param string $id
     * @param array $data
     * @return bool True on success, else false.
     */
    public function write(string $id, array $data) : bool;

    /**
     * Destroy the session identified by `$id`.
     *
     * @param string $id
     * @return bool True on success, else false.
     */
    public function destroy(string $id) : bool;

    /**
     * Perform garbage collection for all sessions older than `$maxlifetime`
     * seconds.
     *
     * @param int $maxlifetime
     * @return bool True on success, else false.
     */
    public function gc(int $maxlifetime) : bool;
}

