<?php

/**
 * Simple PHP session handler.
 *
 * This is just a front-end to PHP's built-in session handling.
 * It sucks, but some applications will want to use it sometimes.
 *
 * @package monolyth
 * @author Marijn Ophorst <marijn@monomelodies.nl>
 * @copyright MonoMelodies 2010, 2011, 2012, 2014
 */

namespace monolyth;
use Project

class PHP_Session_Model
{
    /**
     * The period after which a session should timeout.
     * Defaults to 45 minutes; you can override this in an extended custom
     * SESSION-class.
     */
    const TIMEOUT = '-45 minutes';
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

    /** Initialise a new or existing session. */
    public function init()
    {
        // some configurations have session.auto-start on (bah).
        // first close any possibly open session.
        session_write_close();
        ini_set("session.gc_probability", self::CLEANUP);
        ini_set("session.gc_divisor", self::DIVISOR);
        session_name(Project::$name);
        session_set_cookie_params(0, '/', $this->project['cookiedomain']);
        $id = self::id();
        session_id($id);
        session_start();
    }

    /**
     * A quick and dirty check if the current user-agent is a bot.
     *
     * @return bool True if it looks like a bot, false if it seems okay.
     * @todo Maybe use some sort of config file for the IPs.
     */
    public function is_bot()
    {
        return (
            (
                // check ip
                isset($_SERVER['REMOTE_ADDR']) &&
                in_array(
                    $_SERVER['REMOTE_ADDR'],
                    [
                        '62.212.89.207',   // nasty spider
                        '131.107.0.102',   // MS proxy & hotmail linkchecker???
                        
                        '89.248.99.66',
                        '88.1.73.202',     // spanish IP/open proxy? id's as MSIE6 and 7
                        
                        '83.11.117.214',   // Polish workers? MSIE6/7
                        '77.50.62.88',     // The Russians are coming! MSIE6/7
                        '90.157.199.113',  // Slovanians? MSIE6/7
                    ]
                )
            ) or (
                // check user_agent
                isset($_SERVER['HTTP_USER_AGENT']) &&
                preg_match(
                    <<<EOT
/(
                        spider|crawler|probe|bot|teoma|jeeves|slurp|
                        mediapartners-google|ingrid|aportworm|twiceler|oegp|
                        eknip|vagabondo|shopwiki|ilsebot|internetseer|
                        java\/1\.[456]|check_http|watchmouse|dts agent
)/xi
EOT
                    ,
                    $_SERVER['HTTP_USER_AGENT']
                )
            )
        );
    }
}

