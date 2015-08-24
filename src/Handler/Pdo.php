<?php

namespace Cesession\Handler;

use Cesession\Handler;

class Pdo implements Handler
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    public function read($id)
    {
        static $stmt;
        if (!isset($stmt)) {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM cesession_session WHERE id = :id"
            );
        }
        $stmt->execute(compact('id'));
        if ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            return $data;
        }
        return false;
    }
    
    public function write($id, $data)
    {
        static $create, $update;
        $values = $data + compact('id'); // Default.
        if (!isset($create, $update)) {
            $fields = [];
            $placeholders = [];
            $updates = [];
            foreach ($values as $key => $value) {
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
        }
        // Try update first since that's the most common case.
        $update->execute($values);
        if ($affectedRows = $update->rowCount() and $affectedRows) {
            return true;
        }
        $create->execute($values);
        return ($affectedRows = $create->rowCount()) && $affectedRows;
    }
    
    public function destroy($id)
    {
        static $stmt;
        if (!isset($stmt)) {
            $stmt = $this->pdo->prepace(
                "DELETE FROM cesession_session WHERE id = :id"
            );
        }
        $stmt->execute(compact('id'));
        return ($affectedRows = $stmt->rowCount()) && $affectedRows;
    }
    
    public function gc()
    {
        static $stmt;
        if (!isset($stmt)) {
            $stmt = $this->pdo->prepare(
                "DELETE FROM cesession_session WHERE dateactive < ?"
            );
        }
        $stmt->execute([date('Y-m-d H:i:s', strtotime('-45 minute'))]);
        return ($affectedRows = $stmt->rowCount()) && $affectedRows;
    }
}

