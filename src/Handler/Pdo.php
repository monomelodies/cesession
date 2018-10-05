<?php

namespace Monolyth\Cesession\Handler;

use Monolyth\Cesession\Handler;
use PDOException;

class Pdo implements Handler
{
    /** @var PDO */
    private $pdo;
    /** @var bool */
    private $exists = false;

    /**
     * Constructor.
     *
     * @param PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Reads the session identified by `$id`, if it exists.
     *
     * @param string $id
     * @return array|null The row read, or null if not found.
     */
    public function read(string $id) :? array
    {
        static $stmt;
        if (!isset($stmt)) {
            $stmt = $this->pdo->prepare("SELECT * FROM cesession_session WHERE id = :id");
        }
        $stmt->execute(compact('id'));
        if ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->exists = true;
            $data['data'] = base64_decode($data['data']);
            return $data;
        }
        $this->exists = false;
        return null;
    }
    
    /**
     * Write back data.
     *
     * @param string $id The session ID.
     * @param array $data Hash of data.
     * @return bool True on success, else false.
     */
    public function write(string $id, array $data) : bool
    {
        static $create, $update, $delete;
        $values = $data + compact('id'); // Default.
        unset($values['dateactive']);
        if (!isset($create, $update)) {
            $fields = [];
            $placeholders = [];
            $updates = [];
            foreach ($values as $key => &$value) {
                if ($key == 'data') {
                    $value = base64_encode($value);
                }
                $fields[] = $key;
                $placeholders[] = ":$key";
                if ($key != 'id') {
                    $updates[] = "$key = :$key";
                }
            }
            $create = $this->pdo->prepare(sprintf(
                "INSERT INTO cesession_session (%s) VALUES (%s)",
                implode(', ', $fields),
                implode(', ', $placeholders)
            ));
            $update = $this->pdo->prepare(sprintf(
                "UPDATE cesession_session SET %s WHERE id = :id",
                implode(', ', $updates)
            ));
            $delete = $this->pdo->prepare("DELETE FROM cesession_session WHERE id = :id");
        }
        if ($this->exists) {
            $update->execute($values);
            return (bool)$update->rowCount();
        } else {
            $delete->execute(compact('id'));
            try {
                $create->execute($values);
                return ($affectedRows = $create->rowCount()) && $affectedRows;
            } catch (PDOException $e) {
                $update->execute($values);
                return (bool)$update->rowCount();
            }
        }
    }
    
    /**
     * Destroy the session identified by `$id`.
     *
     * @param string $id
     * @return bool True on success, else false.
     */
    public function destroy(string $id) : bool
    {
        static $stmt;
        if (!isset($stmt)) {
            $stmt = $this->pdo->prepare("DELETE FROM cesession_session WHERE id = :id");
        }
        $stmt->execute(compact('id'));
        return ($affectedRows = $stmt->rowCount()) && $affectedRows;
    }
    
    /**
     * Run garbase collection.
     *
     * @param int $maxlifetime Maximum number of seconds a session may be
     *  inactive before it is eligible for garabage collection.
     * @return bool True if anything was removed, else false.
     */
    public function gc(int $maxlifetime) : bool
    {
        static $stmt;
        if (!isset($stmt)) {
            $stmt = $this->pdo->prepare(
                "DELETE FROM cesession_session WHERE dateactive < ?"
            );
        }
        $stmt->execute([date('Y-m-d H:i:s', strtotime("-$maxlifetime second"))]);
        return ($affectedRows = $stmt->rowCount()) && $affectedRows;
    }
}

