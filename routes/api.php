<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api','verified');
Route::get('/test-email', function (Request $request) {

    $user = App\Models\User::whereEmail($request->email)->first();
    $user->notify(new App\Notifications\VerifyEmail());
    return response()->json(['message' => 'Email sent']);
});

Route::group([

    'middleware' => 'api',
    'prefix' => 'auth'

], function ($router) {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);


    Route::post('email/resend', [AuthController::class, 'resendVerificationEmail'])->middleware('auth:api');
});




Route::get('/verify-email/{id}/{hash}', function ($id, $hash) {
    $user = User::findOrFail($id);

    if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        return response()->json(['message' => 'Token không hợp lệ'], 400);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'Email đã được xác thực'], 400);
    }

    $user->markEmailAsVerified();
    event(new Verified($user));

    return response()->json(['message' => 'Email đã được xác thực thành công']);
})->name('verification.verify');
