<?php

/**
 * Common Session-handler that more specific handlers should extend.
 *
 * @package cesession
 * @author Marijn Ophorst <marijn@monomelodies.nl>
 * @copyright MonoMelodies 2011, 2012, 2014, 2015
 */

namespace secession;
use Adapter_Access;
use monolyth\utils\Translatable;
use monolyth\adapter\nosql\KeyNotFound_Exception;
use ErrorException;
use monolyth\Config;

abstract class Session_Model
{
    use Translatable;
    use Adapter_Access;

    /** A newly instantiated session. */
    const STATE_NEW = 'new';
    /** An existing session (the default). */
    const STATE_EXISTING = 'existing';
    /** This session was updated. */
    const STATE_UPDATED = 'updated';
    /** This session was deleted. */
    const STATE_DELETED = 'deleted';
    /** This session has expired and should be deleted. */
    const STATE_EXPIRED = 'expired';
    /** This session seems hacked... */
    const STATE_ILLEGAL = 'illegal';

    /**
     * {{{ Constants related to write modes.
     */
    /** Force hard write (default prefers in-memory caching). */
    const WRITEMODE_FORCE = 1;
    /**
     * Fake writing, i.e. only call notify (if applicable) but don't actually
     * attempt to update a database or whatever.
     */
    const WRITEMODE_FAKE = 2;
    /** }}} */

    /**
     * The period after which a session should timeout.
     * Defaults to 45 minutes; you can override this in an extended custom
     * Session Model. The format used is lastmodified < strtotime(TIMEOUT).
     */
    const TIMEOUT = '-45 minutes';
    /**
     * The period after which a session should ALWAYS be synced back
     * to the database. Defaults to five minutes, and is of course ignored
     * if memcached or another caching mechanism isn't available.
     */
    const UPDATE = '-5 minutes';
    /**
     * The garbage collection probability. You can set this pretty low on busier
     * hosts; if it's called on average every 5 minutes, that's cool.
     */
    const CLEANUP = 1;
    /**
     * The garbage collection divisor. It's calculated as follows:
     * self::CLEANUP / self::DIVISOR == probability.
     */
    const DIVISOR = 100;

    private $state = self::STATE_EXISTING;

    /** Internal variables you shouldn't worry about. Really. */
    protected $data = [], $ip, $userid, $user_agent, $dateactive,
        $random = null, $dateactivereal, $id = null, $checksum = null,
        $new = false, $__savecount__ = 0, $__written__ = false;
    /** The startuperror encountered, if any. */
    public $startuperror = null,
           /**
            * Expired sessions (possibly used in Observer-based garbage
            * collection.
            */
           $expireds;

    /**
     * Get/set the current session state.
     *
     * @param string $new If supplied, set the new state. Best uses one of the
     *                    STATE_XXX constants provided.
     * @return string The current or newly set state.
     */
    public function state($new = null)
    {
        if (isset($new)) {
            $this->state = $new;
        }
        return $this->state;
    }

    /**
     * Constructor. Initialise a new or existing session.
     *
     * @return void
     */
    protected function __construct()
    {
        static $inited = false;
        if ($inited) {
            return;
        }
        $inited = true;
        /**
         * Some configurations have session.auto-start on (bah).
         * First close any possibly open session.
         */
        if (ini_get('session.auto_start')) {
            session_destroy();
        }

        static $count = 0;
        $this->dateactive = date('Y-m-d H:i:s');
        try {
            $parts = explode(',', $_SERVER['REMOTE_ADDR']);
            $this->ip = trim(array_pop($parts));
        } catch (ErrorException $e) {
            $this->ip = '0.0.0.0';
            $_SERVER['REMOTE_ADDR'] = '0.0.0.0';
        }
        $this->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ?
            substr($_SERVER['HTTP_USER_AGENT'], 0, 255) :
            'unknown';
        ini_set("session.gc_probability", self::CLEANUP);
        ini_set("session.gc_divisor", self::DIVISOR);
        ini_set("session.save_handler", "user");
        session_set_save_handler(
            [$this, 'open'],
            [$this, 'close'],
            [$this, 'read'],
            [$this, 'write'],
            [$this, 'destroy'],
            function($max_lifetime) { return 0; }
        );
        session_name(\Project::instance()['site']);
        $config = Config::get('monolyth');
        session_set_cookie_params(
            0,
            '/',
            \Project::instance()['cookiedomain'],
            false,
            $config->cookie_http_only
        );
        $this->id();
    }

    protected function instantiate($found, $q)
    {
        $expire = strtotime(self::TIMEOUT);
        if (
            $found and
            $q['user_agent'] == $this->user_agent and
            strtotime($q['dateactive']) > $expire
        ) {
            $this->new = false;
            $this->data = $q;
            $this->userid = $q['userid'];
            $this->random = $q['randomid'];
            $this->dateactive = $q['dateactive'];
            if (isset($q['__savecount__'])) {
                $this->__savecount__ = $q['__savecount__'];
            }
        } else {
            $this->new = true;
            if ($found) { // invalidated session
                if ($q['user_agent'] != $this->user_agent) {
                    $this->startuperror = 'hijacked';
                } elseif (strtotime($q['dateactive']) < $expire) {
                    $this->startuperror = 'expired';
                }
                $this->destroy($this->id, $this->random);
                $this->id = null;
            }
        }

        /**
         * Check to see if we need to do garbage collection. This is called
         * manually since otherwise we won't have all our objects anymore.
         * Its probability is calculated by dividing self::CLEANUP by
         * self::DIVISOR, mulitplying that by 100 and checking to see if
         * it's smaller than or equal to a random number between 1 and 100.
         * E.g., for default settings this would translate to 1 / 100 * 100
         * (i.e., 1) <= rand(1, 100), or on average cleanup once every 100
         * requests. You can easily customise this by creating a custom Session
         * Model extending of the models in monolyth\Model/Session and changing
         * these constants. E.g., setting either self::CLEANUP to 5 or
         * self::DIVISOR to 20 would trigger cleanup once every 5 requests on
         * average.
         */
        if ((self::CLEANUP / self::DIVISOR) * 100 <= rand(1, 100)) {
            $this->gc(self::TIMEOUT);
        }
        $id = $this->id();
        session_id($id);
    }

    protected function getFromCache(&$q)
    {
        if ($cache = self::cache()) {
            try {
                $q = json_decode(
                    $cache->get("session/{$this->id}/{$this->random}"),
                    true
                );
                if (isset($q['user_agent'], $q['dateactive'])) {
                    return true;
                }
            } catch (KeyNotFound_Exception $e) {
            } catch (ErrorException $e) {
            }
        }
        return null;
    }

    protected function saveToCache($fields, $force = false)
    {
        if ($cache = self::cache()
            and $cache->set(
                "session/{$this->id}/{$this->random}",
                json_encode([
                    '__savecount__' => $this->__savecount__,
                    'user_agent' => $this->user_agent,
                    'dateactive' => date('Y-m-d H:i:s'),
                    'dateactivereal' => time(),
                    'userid' => $this->userid,
                    'randomid' => $this->random,
                ] + $fields),
                // After this, the session timeouts anyway...
                45 * 60
            )
            and (strtotime($this->dateactive) > strtotime(self::UPDATE)
                && $this->__savecount__ % 10
                && !($force || $_POST)
            )
        ) {
            return true;
        }
        return null;
    }

    protected function fillDefaults()
    {
        $this->data = [
            'id' => substr($this->id, 0, 32),
            'randomid' => $this->random,
            'userid' => null,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'checksum' => md5(''),
            'data' => '',
        ];
    }

    /**
     * Start a new session.
     *
     * @return bool|int True if it's an already existing session, false if no
     *                  session is possible or num_rows for inserting if the
     *                  database handling the session supports that.
     */
    public function open()
    {
        if ($this->isBot()) {
            return false;
        }
        if ($this->new) {
            $this->fillDefaults();
            return $this->create();
        }
        return true;
    }

    /**
     * Get or set the session's checksum.
     *
     * @param string $checksum The checksum to set.
     * @return bool|string True when setting, the checksum when getting.
     */
    protected function checksum($checksum = null)
    {
        if (isset($checksum)) {
            $this->data['checksum'] = $checksum;
            return true;
        }
        return $this->data['checksum'];
    }

    /**
     * Get the current session id. If no id exists yet,
     * it will be generated.
     *
     * @return string The current session id.
     */
    public function id()
    {
        if (
            isset($this->id, $this->random) and
            strlen($this->id) && strlen($this->random)
        ) {
            return $this->id.$this->random;
        }
        /**
         * Id was passed from a different domain (for multi-domain session
         * persistance).
         */
        if (isset($_GET['sid'])) {
            $_GET['sid'] = preg_replace("@[^a-zA-Z0-9-]@", '', $_GET['sid']);
            $this->id = substr($_GET['sid'], 0, 32);
            $this->random = substr($_GET['sid'], 32);
            return $_GET['sid'];
        }        
        $long = $this->buildid();
        $this->id = substr($long, 0, 32);
        $this->random = substr($long, 32);
        return $long;
    }

    /**
     * Helper method to build a unique session id.
     *
     * @return string A unique session id.
     */
    protected function buildid()
    {
        if (!$this->new && isset($_COOKIE[session_name()])) {
            return preg_replace(
                "@[^a-zA-Z0-9-]@",
                '',
                $_COOKIE[session_name()]
            );
        }
        if (!(isset($this->random) and strlen($this->random))) {
            $this->random = rand(0, 99999);
        }
        return md5(sprintf(
            '%d %s %s %d',
            time(),
            $_SERVER['REMOTE_ADDR'],
            $this->user_agent,
            $this->random
        )).$this->random;
    }

    /**
     * Get or set session userdata.
     *
     * There are three ways to call this method:
     * - with no arguments; it will return a hash of values.
     * - with one argument; query for that argument. Valid values in this
     *   version are ip, user_agent and userid.
     * - with two or three arguments; set ip, user_agent and userid
     *   (in that order).
     *
     * @param string $argument The argument to query, or an IP to set if calling
     *                         with three arguments.
     * @param string $user_agent The user_agent to set.
     * @param string $userid The userid to set.
     * @return mixed True when setting multiple values. If called with a single
     *               argument it returns its value, or false if the argument
     *               was invalied. When called without arguments, it returns a
     *               hash of ip, user_agent and userid.
     */
    public function userdata()
    {
        $args = func_get_args();
        if (count($args) > 1) {
            $this->ip = $args[0];
            $this->user_agent = $args[1];
            $this->userid = isset($args[2]) ? $args[2] : NULL;
            return true;
        } elseif (count($args) == 1) {
            switch ($args[0]) {
                case 'ip': return $this->ip;
                case 'user_agent' : return $this->user_agent;
                case 'userid': return $this->userid;
            }
            return false;
        } else {
            return [
                'ip' => $this->ip,
                'user_agent' => $this->user_agent,
                'userid' => $this->userid,
            ];
        }
    }

    protected function getSuspectSessions()
    {
        return 0;
    }

    /**
     * Close the session.
     * Note that this is really a stub, we don't need to do much here.
     *
     * @return bool True if we actually have a session, false if not.
     */
    public function close()
    {
        return !$this->isBot();
    }

    /**
     * Read the session.
     *
     * @param string $id The session id.
     */
    public function read($id)
    {
        try {
            $data = $this->id.$this->random == $id && $this->data['data'] ?
                unserialize(base64_decode($this->data['data'])) :
                [];
        } catch (ErrorException $e) {
            $data = [];
        }
        $this->data['data'] = is_array($data) ? $data : [];
        $_SESSION =& $this->data['data'];
        return true;
    }

    /**
     * Method-specific session models should define their own write-method;
     * this one takes care of any attached observers. The parameters are not
     * used but simply kept for consistency.
     *
     * @param string $id The session ID.
     * @param integer $mode The write mode to use. See the
     *                      self::WRITEMODE_XXX constants for bitflag options.
     */
    public function write($id, $mode = 0)
    {
        if (method_exists($this, 'notify')) {
            $this->notify();
        }
        return true;
    }

    /**
     * Stop the current session.
     *
     * There can be many reasons to stop a session; logging out is one of them.
     * Pass an additional $reason so the user may be informed.
     *
     * @param string|null $reason The reason this session was stopped.
     */
    public function stop($reason = null)
    {
        if (isset($reason)) {
            $this->startuperror = $this->text(
                'monolyth/session/generic',
                $reason
            );
        }
        $_SESSION['User'] = null;
        $this->__savecount__ = 0;
    }

    /**
     * A quick and dirty check if the current user-agent is a bot.
     *
     * @return bool True if it looks like a bot, false if it seems okay.
     * @todo Maybe use some sort of config file for the IPs.
     */
    public function isBot()
    {
        static $cached = null;
        if ((isset($_SERVER['REMOTE_ADDR'])
                && in_array(
                    $_SERVER['REMOTE_ADDR'],
                    [
                        '62.212.89.207',   // nasty spider
                        '131.107.0.102',   // MS proxy & hotmail linkchecker???
                        
                        '89.248.99.66',
                        '88.1.73.202',     // spanish IP/open proxy? id's as MSIE6 and 7
                        
                        '83.11.117.214',   // Polish workers? MSIE6/7
                        '77.50.62.88',     // The Russians are coming! MSIE6/7
                        '90.157.199.113',  // Slovanians? MSIE6/7

                        '41.82.70.152', // African tribal warfare
                        '41.208.166.88',
                        '46.4.55.214',
                    ]
                )
            )
            or (isset($_SERVER['HTTP_USER_AGENT'])
                && preg_match(
                    <<<EOT
/(
                        spider|crawler|probe|bot|teoma|jeeves|slurp|
                        mediapartners-google|ingrid|aportworm|twiceler|oegp|
                        eknip|vagabondo|shopwiki|ilsebot|internetseer|
                        java\/1\.[4567]|check_http|watchmouse|dts agent
)/xi
EOT
                    ,
                    $_SERVER['HTTP_USER_AGENT']
                )
            )
        ) {
            return true;
        }
        if (isset($_COOKIE[\Project::instance()['site']])) {
            $cached = false;
        }
        if (!isset($cached)) {
            $count = $this->getSuspectSessions();
            $cached = $count > 10;
        }
        return $cached;
    }
}

