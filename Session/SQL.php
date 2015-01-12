<?php

/**
 * adapter\sql-driven session handler.
 *
 * monolyth uses database-driven sessions by default.
 * Alternatives are available in Session/.
 *
 * @package monolyth
 * @author Marijn Ophorst <marijn@monomelodies.nl>
 * @copyright MonoMelodies 2008, 2009, 2010, 2011, 2012, 2014
 * @see Session
 * @see core\Session
 */

namespace monolyth;
use monolyth\core\Session_Model;
use ErrorException;
use Adapter_Access;

class SQL_Session_Model extends Session_Model
{
    use Adapter_Access;
    use core\Singleton;

    /**
     * Constructor. Initialise a new or existing session.
     *
     * @return void
     */
    protected function __construct()
    {
        parent::__construct();
        $q = null;
        if (!($found = $this->getFromCache($q))) {
            try {
                $q = self::adapter()->row(
                    'monolyth_session',
                    '*',
                    ['id' => $this->id]
                );
                $found = true;
            } catch (adapter\sql\NoResults_Exception $e) {
                $found = false;
                $q = null;
            }
        }
        $this->instantiate($found, $q);
        try {
            session_start();
        } catch (ErrorException $e) {
        }
    }

    public function get($key)
    {
        try {
            return $_SESSION[$key];
        } catch (ErrorException $e) {
            return null;
        }
    }

    public function set($key, $value = null)
    {
        if (is_string($key)) {
            $_SESSION[$key] = $value;
        } elseif (is_array($key)) {
            $_SESSION = $key + $_SESSION;
        }
    }

    public function exists($key)
    {
        return isset($_SESSION[$key]);
    }

    public function all()
    {
        return $_SESSION;
    }

    protected function create()
    {
        $this->state(self::STATE_NEW);
        try {
            return self::adapter()->insert('monolyth_session', $this->data);
        } catch (adapter\sql\Exception $e) {
            /**
             * Session expired but re-insert is attempted,
             * or the id already exists by chance (could happen).
             */
            $this->random = $this->id = null;
            $this->id();
            return $this->open();
        }
    }

    /**
     * Write the session back to the database and/or memcached.
     *
     * @param string $id The session id.
     * @param integer $mode What mode to use. Pass one or more bitflags from
     *                      self::WRITEMODE.
     * @return bool Returns true on success, false on failure.
     */
    public function write($id = null, $mode = 0)
    {
        if (!isset($id)) {
            $id = session_id();
        }
        if ($mode & self::WRITEMODE_FAKE) {
            return parent::write($id, $mode);
        }
        if (!(isset($_SESSION) && isset($this->data))) {
            // Don't try to close the session if we don't have one.
            return true;
        }
        $this->data['data'] = $_SESSION;
        $fields = [];
        $this->userid = $this->data['userid'] =
            isset($this->data['data']['User']['id']) ?
                $this->data['data']['User']['id'] :
                null;
        if ($this->isBot()
            or !isset($this->user_agent, $this->data['user_agent'])
            or $this->user_agent != $this->data['user_agent']
        ) {
            return true;
        }
        if ($this->userid) {
            $fields['userid'] = $this->userid;
        }
        $data = base64_encode(serialize($this->data['data']));
        if (
            $check = md5($data) and
            (
                !isset($this->data['checksum']) ||
                $check != $this->data['checksum']
            )
        ) {
            $mode |= self::WRITEMODE_FORCE;
            $fields['checksum'] = $check;
            $this->state(self::STATE_UPDATED);
        }
        $fields['data'] = $data;
        $fields['dateactive'] = self::adapter()->now();
        if (!isset($this->data['ip']) or $this->ip != $this->data['ip']) {
            $fields['ip'] = $this->ip;
        }
        $this->__savecount__++;
        if (!$this->saveToCache($fields, $mode & self::WRITEMODE_FORCE)) {
            try {
                if ($this->userid) {
                    try {
                        $ids = [];
                        foreach (self::adapter()->rows(
                            'monolyth_session',
                            'id',
                            [
                                'userid' => $this->userid,
                                'id' => ['<>' => $this->id],
                            ]
                        ) as $row) {
                            $this->flush($row['id']);
                            $ids[] = $row['id'];
                        }
                        if ($ids) {
                            self::adapter()->update(
                                'monolyth_session',
                                ['userid' => null],
                                [
                                    'id' => ['IN' => $ids],
                                    'userid' => $this->userid,
                                ]
                            );
                        }
                    } catch (adapter\sql\UpdateNone_Exception $e) {
                    } catch (adapter\sql\NoResults_Exception $e) {
                    }
                }
                self::adapter()->update(
                    'monolyth_session',
                    $fields,
                    ['id' => $this->id]
                );
            } catch (adapter\sql\UpdateNone_Exception $e) {
                /** Session's gone; reset! */
                $this->flush($this->id);
                return false;
            } catch (adapter\sql\Exception $e) {
                /** This is real panic. */
                $this->flush($this->id);
                return false;
            }
        }
        parent::write($id, $mode);
        return true;
    }

    /**
     * Reset the current session.
     *
     * @return void
     */
    public function reset()
    {
        $this->state(self::STATE_DELETED);
        $_SESSION = [];
    }

    /**
     * Destroy a session.
     *
     * @param string $id The session id to destroy.
     * @param int $random The random salt for that session.
     * @return int|bool Number of affected rows on succes, or true if the
     *                  current database doesn't support that; zero or false
     *                  if nothing was deleted.
     */
    public function destroy($id, $random)
    {
        $this->state(self::STATE_DELETED);
        $_COOKIE[session_name()] = null;
        if ($cache = self::cache()) {
           try {
               $cache->delete("session/$id/$random");
            } catch (adapter\nosql\KeyNotFound_Exception $e) {
            }
        }
        $this->__savecount__ = 0;
        try {
            return self::adapter()->delete(
                'monolyth_session',
                ['id' => $id]
            );
        } catch (adapter\sql\DeleteNone_Exception $e) {
            return 0;
        }
    }

    /**
     * The garbage collector.
     *
     * @return int|bool Number of affected rows on succes, or true if the
     *                  current database doesn't support that; zero or false
     *                  if nothing was deleted.
     * @todo Also delete from memcached?
     */
    public function gc($maxlifetime)
    {
        try {
            if (method_exists($this, 'notify')) {
                $this->expireds = self::adapter()->rows(
                    'monolyth_session',
                    '*',
                    ['dateactive' => ['<' => date(
                        'Y-m-d H:i:s',
                        strtotime($maxlifetime)
                    )]]
                );
                $this->notify();
            }
            return self::adapter()->delete(
                'monolyth_session',
                ['dateactive' => ['<' => date(
                    'Y-m-d H:i:s',
                    strtotime($maxlifetime)
                )]]
            );
        } catch (adapter\sql\NoResults_Exception $e) {
            return 0;
        } catch (adapter\sql\DeleteNone_Exception $e) {
            return 0;
        }
    }

    /**
     * 'Flush' an existing session.
     * By flushing it you force values to be re-read. This could be handy
     * when something major changes on a live site, or when some variable
     * is manually edited (permissions for something, maybe).
     *
     * @param string|null $id The id to flush. Defaults to current session
     *                        if omitted.
     */
    public function flush($id = null)
    {
        $this->state(self::STATE_EXPIRED);
        if (!isset($id)) {
            $id = session_id();
        }
        $random = substr($id, 32);
        $id = substr($id, 0, 32);
        if ($cache = self::cache()) {
            try {
                $cache->delete("session/$id/$random");
            } catch (adapter\nosql\KeyNotFound_Exception $e) {
            }
        }
        if (isset($this->userid) && $this->userid) {
            try {
                foreach (self::adapter()->row(
                    'monolyth_auth',
                    '*',
                    ['id' => $this->userid]
                ) as $property => $data) {
                    $_SESSION['User'][$property] = $data;
                }
            } catch (adapter\sql\NoResults_Exception $e) {
                $_SESSION['User'] = null;
                try {
                    self::adapter()->update(
                        'monolyth_session',
                        ['userid' => null],
                        compact('id') + ['randomid' => (int)$random]
                    );
                } catch (adapter\sql\UpdateNone_Exception $e) {
                    /** That's okay, the session might have been updated already. */
                }
            }
        } else {
            try {
                unset($_SESSION['User']);
                self::adapter()->update(
                    'monolyth_session',
                    ['userid' => null],
                    compact('id') + ['randomid' => (int)$random]
                );
            } catch (adapter\sql\UpdateNone_Exception $e) {
            }
        }
    }

    protected function getSuspectSessions()
    {
        if ($_SERVER['REMOTE_ADDR'] == 'unknown') {
            return 0;
        }
        return self::adapter()->field(
            'monolyth_session',
            'count(*)',
            [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $this->user_agent,
            ]
        );
    }
}

