<?php

require_once __DIR__ . "/ardsc.php";

$router = new Router();

$middleware = function ($next, $request) {
    return $next($request);
};

$router->get("/", $middleware, function ($req) {
    return "Hello, Wordl!";
});

$router->run();
