<?php

use App\Routers\Router;

return static function (Router $router): void {

    $adminRoutes = require __DIR__ . '/admin_routes.php';
    $adminRoutes($router);

    $apiRoutes = require __DIR__ . '/api_routes.php';
    $apiRoutes($router);

    $router->get('v1', '/test', 'TestController', 'index');
    $router->get('v1', '/swagger.html', \App\Controllers\SwaggerController::class, 'index');
    $router->get('v1', '/docs/swagger.yaml', \App\Controllers\SwaggerController::class, 'yaml');
    
    // Public utility tools (no auth required)
    $router->get('v1', '/base64_converter.html', \App\Controllers\UtilityController::class, 'base64Converter');
    $router->get('v1', '/diagnostics', \App\Controllers\DiagnosticsController::class, 'index');
    $router->get('v1', '/logs', \App\Controllers\LogController::class, 'index');
    $router->get('v1', '/benchmark', \App\Controllers\BenchmarkController::class, 'index');
};