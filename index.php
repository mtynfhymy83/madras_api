<?php

use App\Middlewares\AuthMiddleware;
use App\Routers\Router;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/App/Helpers/Helper.php';
require_once __DIR__ . '/App/Middlewares/AuthMiddleware.php';
require_once __DIR__ . '/App/Routers/Router.php';

$router = new Router();

// Register routes via callback
$routes = require __DIR__ . '/App/Routers/routes.php';
if (is_callable($routes)) {
    $routes($router);
}

// Resolve request
$requestMethod = $_SERVER["REQUEST_METHOD"];
$version = getApiVersion();
$path = getPath(false);

// Run auth middleware before dispatch (if needed)
$authMiddleware = new AuthMiddleware();
$request = getTokenFromRequest();
$authMiddleware->handle($request);

$router->resolve($version, $requestMethod, $path);