<?php
declare(strict_types=1);

namespace App\Core;

use Swoole\Http\Request;
use Swoole\Http\Response;

interface MiddlewareInterface
{
    /**
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return mixed
     */
    public function handle(Request $request, Response $response, callable $next);
}
