<?php
declare(strict_types=1);

namespace App\Core;

use Swoole\Http\Request;
use Swoole\Http\Response;

class Pipeline
{
    private array $middlewares = [];

    public function through(array $middlewares): self
    {
        $this->middlewares = $middlewares;
        return $this;
    }

    public function then(Request $request, Response $response, callable $destination): void
    {
        $index = 0;
        $middlewares = $this->middlewares;

        $next = function (Request $req, Response $res) use (&$index, &$next, $middlewares, $destination) {
            if ($index >= count($middlewares)) {
                return $destination($req, $res);
            }

            $middleware = $middlewares[$index];
            $index++;

            if ($middleware instanceof MiddlewareInterface) {
                return $middleware->handle($req, $res, $next);
            }

            return $next($req, $res);
        };

        $next($request, $response);
    }
}
