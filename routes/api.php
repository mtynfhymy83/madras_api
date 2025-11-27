<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/auth/eta-login', [AuthController::class, 'etaLogin']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);
Route::post('/auth/logout', [AuthController::class, 'logout']);

// Debug routes (only in debug mode)
if (config('app.debug')) {
    Route::get('/auth/generate-test-data', [AuthController::class, 'generateTestData']);
    Route::post('/auth/test-validation', [AuthController::class, 'testEtaValidation']);
}

// Protected routes (require JWT authentication)
Route::middleware('jwt.auth')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
});


