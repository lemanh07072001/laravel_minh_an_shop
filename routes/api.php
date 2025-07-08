<?php

use App\Models\User;
use Illuminate\Http\Request;
use App\Enums\StatusUserEnum;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SocialController;
use Illuminate\Support\Facades\Notification;
use App\Http\Controllers\GoogleAuthController;
use Illuminate\Auth\Notifications\VerifyEmail;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::group([

    'middleware' => 'api',
    'prefix' => 'auth'

], function ($router) {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/profile', [AuthController::class, 'profile'])->middleware('checkAdmin');
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::post('email/resend', [AuthController::class, 'resendVerificationEmail']);

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');

    // Login Google
    Route::get('/google', [SocialController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [SocialController::class, 'handleGoogleCallback']);

});

Route::controller(UserController::class)->middleware(['auth:api','checkAdmin','locale'])->prefix('user')->group(function () {
    Route::get('/get-users', 'getUser');
    Route::post('/create-user', 'createUser');
    Route::post('/edit-user/{id}', 'editUser');
    Route::get('/info-user/{id}', 'getInfoUser');
    Route::post('/send-email/{id}', 'sendEmail');
    Route::post('/ban-account/{id}', 'banAccount');
    Route::get('/get-account-ban/{id}', 'getAccountBan');
    Route::delete('/delete/{id}', 'deleteUser');
    Route::post('/unban/{id}', 'unBan');
    Route::delete('/delete-multiple/{ids}', 'deleteMultiple');
});


Route::get('/status/user', function (Request $request) {

    return response()->json( StatusUserEnum::asSelectArray());
})->middleware('locale');

