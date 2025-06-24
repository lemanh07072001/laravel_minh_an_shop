<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use App\Events\NewNotification;
use App\Notifications\VerifyEmail;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class AuthController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api', except: ['login', 'register', 'refresh', 'forgotPassword']),
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
            $credentials = $request->only('email', 'password');

            $user = \App\Models\User::where('email', $credentials['email'])->first();


            if ($user && $user->bans()->exists()) {
                $userBan = $user->bans()->first();

                if ($userBan->lock_time == -1) {
                    return response()->json([
                        'message' => 'Tài khoản đã bị khoá vĩnh viễn. Vui lòng liên hệ Admin.',
                    ], 404);
                }

                return response()->json([
                    'message' => 'Tài khoản đã bị khóa ' . CarbonInterval::minutes($userBan->lock_time)->cascade()->forHumans(),
                ], 404);
            }


            if (!$user) {
                return response()->json([
                    'message' => 'Email không tồn tại trong hệ thống.',
                ], 404);
            }

            if (!auth('api')->attempt($credentials)) {
                return response()->json([
                    'message' => 'Tài khoản hoặc mật khẩu không chính xác.',
                ], 401);
            }

            $user = auth('api')->user();

             // Phát sự kiện thông báo
            //  event(new NewNotification("Email $user->email đã đăng nhập thành công!",$user->id));

            return $this->respondWithToken(auth('api')->tokenById($user->id), $user);
        } catch (\Exception $e) {
            logger('Controller: AuthController, Method: login, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());

            return response()->json([
                'message' => 'Đã xảy ra lỗi trong quá trình đăng nhập.',
                'status' => false
            ], 500);
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


    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json([
                'success' => true,
                'message' => 'Link đặt lại mật khẩu đã được gửi.'
                ])
            : response()->json([
                'success' => false,
                'message' => 'Vui lòng thử lại sau ít phút.'
            ], 400);
    }


    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => bcrypt($password)
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json([
                'success' => true,
                'message' => 'Mật khẩu đã được đặt lại thành công.'
            ])
            : response()->json([
                'success' => false,
                'message' => 'Token không hợp lệ hoặc đã hết hạn.'
            ], 400);
    }


}
