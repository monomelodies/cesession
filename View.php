<?php

namespace cesession;

class View
{
    public function __construct()
    {
        if (isset($_SESSION['User'])) {
            foreach ([
                'pass', 'salt', 'ipcreated', 'ipmodified', 'ipactive',
            ] as $remove) {
                unset($_SESSION['User'][$remove]);
            }
        }
        if (isset($_SESSION['Groups'])) {
            unset($_SESSION['Groups']);
        }
        $_SESSION['_id'] = session_id();
        $this->jsonData = $_SESSION;
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

