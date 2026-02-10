<?php

namespace App\Controllers;
use App\Controllers\Controller;
use App\Cache\Cache;

class UserController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index(Request $req, Response $res)
    {

        $params = $req->get ?? [];


        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $limit = isset($params['limit']) ? (int)$params['limit'] : 20;


        $result = $this->userRepo->getPaginatedWithStats($params, $page, $limit);

        return $this->json($res, $result);
    }

    public function get($id, $request){
        $user = $this->queryBuilder->table('users')->where('users.id', '=', $id)->cache(600)->get()->execute(); // Cache for 10 minutes

        return $this->sendResponse(data: $user, message: "کاربر شما با موفقیت دریافت شد");
    }

    public function store($request){
        $this->validate([
            'username||required|min:3|max:25|string',
            'display_name||min:2|max:40|string',
            'mobile_number||required|length:11|string',
            'role||enum:admin,support,guest,host',
            'status||enum:pending,reject,accept'
        ], $request);

        $this->checkUnique(table: 'users' ,array: [['username', $request->username], ['mobile_number', $request->mobile_number]]);

        // check profile image
        if($request->profile_image){
            $request->profile_image = uploadBase64($request->profile_image, 'uploads/profile_image');
        }

        $newUser = $this->queryBuilder->table('users')
            ->insert([
                'username' => $request->username,
                'display_name' => $request->display_name ?? NULL,
                'mobile_number' => $request->mobile_number,
                'profile_image' => $request->profile_image ?? NULL,
                'role' => $request->role ?? 'guest',
                'status' => $request->status ?? 'pending',
                'created_at' => time(),
                'updated_at' => time()
            ])->execute();

        return $this->sendResponse(data: $newUser, message: "  کاربر جدید با موفقیت ایجاد شد!");
    }

    public function update($id, $request)
    {
        $this->validate([
            'display_name||min:2|max:40|string',
        ], $request);

        $user = $this->queryBuilder->table('users')->where('users.id', '=', $id)->get()->execute();

        // check profile image
        if($request->profile_image && $request->profile_image != $user->profile_image){
            $request->profile_image = uploadBase64($request->profile_image, 'uploads/profile_image');
        }

        $newUser = $this->queryBuilder->table('users')
            ->update([
                'username' => $request->username ?? $user->username,
                'display_name' => $request->display_name ?? $user->display_name,
                'mobile_number' => $request->mobile_number ?? $user->mobile_number,
                'profile_image' => $request->profile_image ?? $user->profile_image,
                'role' => $request->role ?? $user->role,
                'status' => $request->status ?? $user->status,
                'updated_at' => time()
            ])->where('id', '=', $id)->execute();

        // Clear user-related caches
        Cache::clear();

        return $this->sendResponse(data: $newUser, message: "کاربر با موفقیت ویرایش شد");
    }
    public function destroy($id){
        $deletedUser = $this->queryBuilder->table('users')
            ->update([
                'deleted_at' => time()
            ])->where('id', '=', $id)->execute();

        // Clear user-related caches
        Cache::clear();

        return $this->sendResponse(data: $deletedUser , message: "کاربر با موفقیت حذف شد");
    }
}