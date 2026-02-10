<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\UserService;
use Swoole\Http\Request;
use Swoole\Http\Response;

class UserController extends Controller
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function index($request)
    {
        $queryParams = [];

        if (is_array($request)) {
            $queryParams = $request;
        } elseif (is_object($request) && isset($request->get) && is_array($request->get)) {
            $queryParams = $request->get;
        }

        $result = $this->userService->getUsersList($queryParams);

        return $result;
    }
}