<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialController extends Controller
{
    public function redirectToGoogle()
    {
        return response()->json([
            'url' => Socialite::driver('google')->stateless()->redirect()->getTargetUrl()
        ]);
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $user = User::where('email', $googleUser->email)->first();

            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'password' => bcrypt(uniqid()),
                ]);
            } else {
                $user->update(['google_id' => $googleUser->id]);
            }

            $token = auth('api')->login($user);

            return response()->json([
                'user' => $user,
                'token' => $token,
                'expires_in' => auth('api')->factory()->getTTL() * 60
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Đăng nhập thất bại: ' . $e->getMessage()
            ], 500);
        }
    }
}
