<?php

namespace cesession;
use reroute\Router;

if (!isset($router)) {
    $router = new Router;
}
if ($router instanceof Router) {
    $router->group('cesession.api', function($router) {
        $router->state('cesession', '/cesession/', function() {
            return new View;
        });
        $router->state('cesession', '/cesession/:POST', function() {
            $controller = new Controller;
            $controller();
            return new View;
        });
    });
}

