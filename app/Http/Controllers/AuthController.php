<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Notifications\VerifyEmail;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class AuthController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api', except: ['login', 'register', 'refresh']),
            new Middleware('verified', except: ['register', 'login', 'resendVerificationEmail']),
        ];
    }

    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|confirmed',
        ], [
            'username.required' => 'Tên không được để trống.',
            'username.string' => 'Tên phải là kiểu chuỗi',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email đã tồn tại.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ]);

        try {
            $user = User::create([
                'name'     => $request->username,
                'email'    => $request->email,
                'password' => bcrypt($request->password),
            ]);

            $user->sendEmailVerificationNotification();

            return response()->json([
                'status' => true,
                'message' => 'Đăng ký thành công.',
            ]);
        } catch (\Exception $e) {
            logger('Controller: AuthController, Method: register, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'password.required' => 'Mật khẩu không được để trống.',
        ]);

        try {
            $credentials = request(['email', 'password']);

            if (! $token = auth('api')->attempt($credentials)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            $user = auth('api')->user();
            return $this->respondWithToken($token, $user);
        } catch (\Exception $e) {
            logger('Controller: AuthController, Method: login, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());
        }
    }

    public function profile()
    {
        try {
            return response()->json(auth('api')->user());
        } catch (\Exception $e) {
            logger('Controller: AuthController, Method: profile, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());
        }
    }

    public function refresh()
    {
        $user = auth('api')->user();
        $token = auth('api')->refresh();
        return $this->respondWithToken($token, $user);
    }

    public function logout()
    {
        try {
            auth('api')->logout();
            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            logger('Controller: AuthController, Method: logout, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());
        }
    }

    protected function respondWithToken($token, $user)
    {
        return response()->json([
            'success' => true,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => $user
        ]);
    }


    public function resendVerificationEmail(Request $request)
    {
        $user = auth('api')->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified'], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent']);
    }
}
