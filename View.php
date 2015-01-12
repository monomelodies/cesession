<?php

namespace cesession;
use disclosure\Injector;

class View
{
    use Injector;

    public function __construct()
    {
        $session = new \StdClass;
        /*
        $this->inject('session');
        $session = $this->session;
        if (isset($session['User'])) {
            foreach ([
                'pass', 'salt', 'ipcreated', 'ipmodified', 'ipactive',
            ] as $remove) {
                unset($session['User'][$remove]);
            }
        }
        if (isset($session['Groups'])) {
            unset($session['Groups']);
        }
        $session['_id'] = session_id();
        // These are already pass in $session by now:
        self::message()->get();
        */
        $this->jsonData = $session;
    }

    public function __invoke()
    {
        header("Content-type: application/json", true);
        /*
        if (isset($_SERVER['HTTP_ORIGIN'])
            && in_array($_SERVER['HTTP_ORIGIN'], $project['origins'])
        ) {
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        } else {
            header("Access-Control-Allow-Origin: {$project['http']}");    
        }
        */
        header("Access-Control-Allow-Headers: X-Requested-With");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        echo json_encode($this->jsonData, JSON_NUMERIC_CHECK);

    }
}

