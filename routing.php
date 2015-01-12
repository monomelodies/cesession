<?php

namespace cesession;
use reroute\Router;

if (!isset($router)) {
    $router = new Router;
}
if ($router instanceof Router) {
    $router->group('cesession.api', function($router) {
        $router->state('cesession', '/cesession/', function() {
            return new View(new Session);
        });
        $router->state('cesession', '/cesession/:POST', function() {
            $session = new Session;
            $controller = new Controller($session);
            $controller();
            return new View($session);
        });
    });
}

