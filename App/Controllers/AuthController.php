<?php

namespace App\Controllers;

use App\Contracts\AuthServiceInterface;
use App\Controllers\Controller;
use App\Requests\Auth\EitaaLoginRequest;
use App\Requests\Auth\LoginRequest;
use App\Resources\UserResource;
use App\Services\AuthService;
use Exception;

class AuthController extends Controller
{
    private AuthServiceInterface $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
    }

    public function test(){
        return $this->sendResponse(null, "API is run..", false, HTTP_OK);
    }

    public function login($request)
    {

        if ($request === null) {
            return $this->sendResponse(null, "درخواست نامعتبر است", true, HTTP_BadREQUEST);
        }


        if (is_array($request)) {
            $request = (object) $request;
        }


        $this->validate([
            'username' => 'required|string|min:3|max:64',
            'password' => 'required|string|min:6|max:120'
        ], $request);


        if (!isset($request->username) || empty($request->username)) {
            return $this->sendResponse(null, "نام کاربری الزامی است", true, HTTP_BadREQUEST);
        }

        if (!isset($request->password) || empty($request->password)) {
            return $this->sendResponse(null, "رمز عبور الزامی است", true, HTTP_BadREQUEST);
        }

        try {
            $tokens = $this->authService->loginWithCredentials(LoginRequest::fromObject($request));
            return $this->sendResponse($tokens, "ورود با موفقیت انجام شد");
        } catch (Exception $exception) {
            return $this->sendResponse(null, $exception->getMessage(), true, HTTP_Unauthorized);
        }
    }

    public function register($request){
        if ($request === null) {
            return $this->sendResponse(null, "درخواست نامعتبر است", true, HTTP_BadREQUEST);
        }

        
        if (is_array($request)) {
            $request = (object) $request;
        }

        
        $this->validate([
            'username' => 'required|min:3|max:25|string',
            'password' => 'required|string|min:6|max:120',
            'mobile_number' => 'required|length:11|string',
            'display_name' => 'min:2|max:40|string'
        ], $request);

        
        if (!isset($request->username) || empty(trim($request->username))) {
            return $this->sendResponse(null, "نام کاربری الزامی است", true, HTTP_BadREQUEST);
        }

        if (!isset($request->password) || empty($request->password)) {
            return $this->sendResponse(null, "رمز عبور الزامی است", true, HTTP_BadREQUEST);
        }

        if (!isset($request->mobile_number) || empty(trim($request->mobile_number))) {
            return $this->sendResponse(null, "شماره موبایل الزامی است", true, HTTP_BadREQUEST);
        }

        $this->checkUnique(table: 'users' ,array: [['username', $request->username], ['mobile_number', $request->mobile_number]]);

        
        $passwordHash = sha1($request->password);
        

        $level = '1'; // Default to regular user
        if (isset($request->role) && ($request->role === 'admin' || $request->role === 'support')) {
            $level = '2'; // Admin level
        } elseif (isset($request->level)) {
            
            $level = (string)$request->level;
        }

        $insertSuccess = $this->queryBuilder->table('users')
            ->insert([
                'username' => trim($request->username),
                'displayname' => isset($request->display_name) && !empty(trim($request->display_name)) ? trim($request->display_name) : NULL,
                'tel' => trim($request->mobile_number),
                'avatar' => isset($request->profile_image) && !empty($request->profile_image) ? $request->profile_image : NULL,
                'password' => $passwordHash,
                'level' => $level,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        if (!$insertSuccess) {
            return $this->sendResponse(
                data: null,
                message: "خطا در ایجاد حساب کاربری",
                error: true,
                status: 500
            );
        }

        // Get the newly created user
        $newUser = $this->queryBuilder->table('users')
            ->where('username', '=', trim($request->username))
            ->first();

        // Remove sensitive data
        if ($newUser && isset($newUser->password)) {
            unset($newUser->password);
        }
        
        // Convert object to array for response
        $userData = $newUser ? (array)$newUser : null;

        return $this->sendResponse(
            data: $userData,
            message: "حساب کاربری شما با موفقیت ایجاد شد!"
        );
    }

    public function eitaaLogin($request)
    {
        if ($request === null) {
            throw new \InvalidArgumentException("Invalid request");
        }

        // Convert array to object if needed
        if (is_array($request)) {
            $request = (object) $request;
        }

        $this->validate([
            'eitaa_data' => 'required|string'
        ], $request);

        $tokens = $this->authService->authenticateWithEitaa(EitaaLoginRequest::fromObject($request));
        return $tokens;
    }

    public function me()
    {
        $requestToken = getPostDataInput();
        if (!$requestToken || !isset($requestToken->user_detail->id)) {
            return $this->sendResponse(null, "کاربر یافت نشد", true, HTTP_Unauthorized);
        }

        $user = $this->authService->getUserProfile((int)$requestToken->user_detail->id);
        if (!$user) {
            return $this->sendResponse(null, "کاربر یافت نشد", true, HTTP_NotFOUND);
        }

        return $this->sendResponse(['user' => UserResource::make($user)->toArray()]);
    }

    public function logout($request)
    {
        $this->authService->logout();
        return $this->sendResponse(null, "خروج با موفقیت انجام شد");
    }

    public function logoutAll($request)
    {
        $requestToken = getPostDataInput();
        if (!$requestToken || !isset($requestToken->user_detail->id)) {
            return $this->sendResponse(null, "کاربر یافت نشد", true, HTTP_Unauthorized);
        }

        $this->authService->logoutFromAllDevices((int)$requestToken->user_detail->id);
        return $this->sendResponse(null, "خروج از همه دستگاه‌ها با موفقیت انجام شد");
    }

    public function verify($request){
        $this->validate([
            'token' => 'required|string'
        ], $request);

        $verification = $this->authService->verifyToken($request->token);

        if (!$verification) {
            return $this->sendResponse(null, "توکن نامعتبر است", true, HTTP_Unauthorized);
        }

        return $this->sendResponse(['payload' => $verification], "توکن معتبر است");
    }
}