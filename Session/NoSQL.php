<?php

/**
 * adapter\nosql-driven session handler.
 *
 * Alternative for the default sql-driven session handler.
 *
 * @package monolyth
 * @author Marijn Ophorst <marijn@monomelodies.nl>
 * @copyright MonoMelodies 2011, 2012
 * @see core\Session
 */

namespace monolyth;

class NoSQL_Session_Model extends core\Session implements Cache_Access
{
    /** Initialise a new or existing session. */
    public function init()
    {
        parent::init();
        $q = null;
        if (!($found = $this->getFromCache($q))) {
            try {
                $q = $this->cache->get('monolyth_session/'.$this->id);
                $found = true;
            } catch (adapter\nosql\KeyNotFound_Exception $e) {
                $found = false;
                $q = null;
            }
        }
        $this->instantiate($found, $q);
    }

    protected function create()
    {
        try {
            return $this->cache->set(
                'monolyth_session/'.$this->id,
                $this->data
            );
        } catch (adapter\nosql\Exception $e) {
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
     * @return bool Returns true on success, false on failure.
     */
    public function write($id, $force = false)
    {
        static $written = false;
        if ($written && !$force) {
            return;
        }
        $written = true;
        if (!isset($_SESSION, $this->data)) {
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
        $data = base64_encode(serialize($this->data['data']));
        $this->userid = $this->data['userid'];
        $this->__savecount__++;
        if (
            $this->cache->set(
                sprintf(
                    '%s/session/%s/%d',
                    $this->project['site'],
                    $this->id,
                    $this->random
                ),
                json_encode([
                    'data' => $data,
                    '__savecount__' => $this->__savecount__,
                    'user_agent' => $this->user_agent,
                    'dateactive' => date('Y-m-d H:i:s'),
                    'dateactivereal' => time(),
                    'userid' => $this->userid,
                    'randomid' => $this->random,
                ] + $fields)
            ) and (
                /** Last sync too long ago? */
                strtotime($this->dateactive) > strtotime(self::UPDATE) &&
                /** At least sync once every ten requests. */
                $this->__savecount__ % 10 &&
                /** When forcing, always sync. */
                !$force
            )
        ) {
            return true;
        }
        }
        return true;
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
        $_COOKIE[session_name()] = null;
        try {
            $this->cache->delete(
                "session/{$this->project['site']}/$id$random",
                []
            );
        } catch (adapter\nosql\KeyNotFound_Exception $e) {
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
        return 0;
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
        if (!isset($id)) {
            $id = $this->id.$this->random;
        }
        $this->cache->delete("session/{$this->project['site']}/$id", []);
        if (isset($this->userid) && $this->userid) {
            try {
                $o = new Auth();
                $o->load(['id' => $this->userid]);
                $user = $this->get('User');
                foreach ($o->schema() as $property => $data) {
                    $user[$property] = $data->current;
                }
                $this->set('User', $user);
            } catch (adapter\sql\NoResults_Exception $e) {
                $this->set('User', null);
            }
        }
    }
}

