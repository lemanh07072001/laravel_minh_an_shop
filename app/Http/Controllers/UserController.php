<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Mail\SendEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    public function getUser(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $role = $request->input('role');
        $perPage = $request->input('per_page', 10);


        $users = User::query()->with('bans', 'profile');

        // Tìm kiếm theo name hoặc email
        $users->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        });

        // Tìm kiếm theo status
        $users->when($status !== null, function ($query) use ($status) {
            $query->where('status', $status);
        });

        // Tìm kiếm theo role
        $users->when($role !== null, function ($query) use ($role) {
            $query->where('role', $role);
        });


        // Paginate
        $data = $users->paginate($perPage);
        return response()->json([
            'data' => $data->items(),
            'total' => $data->total(),
            'per_page' => $data->perPage(),
            'current_page' => $data->currentPage(),
        ]);
    }

    public function createUser(UserRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $password = $request->filled('password') ? $request->input('password') : '123456';

                $user = User::create([
                    'name'     => $request['name'],
                    'email'    => $request['email'],
                    'password' => bcrypt($password),
                    'status'   => $request['status'],
                    'role'     => $request['role'],
                ]);

                $user->profile()->create([
                    'phone'   => $request['phone'],
                    'address' => $request['address'] ?? '', // tránh lỗi nếu null
                    'note'    => $request['note'] ?? '',    // tránh lỗi nếu null
                ]);
            });

            return response()->json(['message' => 'Tạo người dùng mới thành công!'], 200);
        } catch (\Exception $e) {
            logger('Controller: UserController, Method: createUser, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());
            return response()->json([
                'message' => 'Có lỗi xảy ra khi tạo user!',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function editUser(UserRequest $request,$id)
    {
        logger($request->all());
        try {
            DB::transaction(function () use ($request,$id) {
                $user = User::find($id);

                if(!$user){
                    throw new \Exception('User not found!', 404);
                }

                // Nếu password có nhập -> mã hóa, ngược lại giữ password cũ
                $password = $user->password; // mật khẩu cũ
                if ($request->filled('password')) {
                    $password = bcrypt($request->input('password'));
                }


                $user->update([
                    'name'     => $request['name'],
                    'email'    => $request['email'],
                    'password' => $password,
                    'status'   => $request['status'],
                    'role'     => $request['role'],
                ]);

                // updateOrCreate để tránh lỗi nếu đã có profile
                $user->profile()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'phone'   => $request['phone'],
                        'address' => $request['address'] ?? '',
                        'note'    => $request['note'] ?? '',
                    ]
                );

                return $user; // Trả về user sau update
            });

            return response()->json([
                'message' => 'Cập nhật user thành công!',
            ]);

        } catch (\Exception $e) {
            logger('Controller: UserController, Method: editUser, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());
            return response()->json([
                'message' => 'Có lỗi xảy ra khi cập nhật user!',
                'error'   => $e->getMessage()
            ], $e->getCode() === 404 ? 404 : 500);
        }
    }

    public function getInfoUser($id)
    {
        try {
            $user = User::with('profile')->find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User not found!'
                ],404);
            }

            return response()->json([
                'data' => $user
            ],200);
        }catch (\Exception $e){
            logger('Controller: UserController, Method: getInfoUser, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());
            return response()->json([
                'message' => 'Có lỗi xảy ra khi lấy user!',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function sendEmail(Request $request, $id){
        try {
            $subject = $request->input('title');
            $message = $request->input('note');
            $viewTemplate =  $request->input('emailTemplate');

            $user = User::find($id);

            if(!$user){
                return response()->json([
                    'message' => 'User not found!'
                ],404);
            }

            $message = str_replace('{{name}}', $user->email, $message);
            Mail::to($user->email)->queue(new SendEmail($subject,$message,$viewTemplate));

            return response()->json([
                'message' => 'Gửi email thành công!',
            ]);
        }catch (\Exception $e){
            logger('Controller: UserController, Method: sendEmail, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());
            return response()->json([
                'message' => 'Có lỗi xảy ra khi lấy user!',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function banAccount(Request $request, $id)
    {
        try {
            $lockTime = $request->input('lockTime');
            $note = $request->input('note');

            $user = User::find($id);

            if(!$user){
                throw new \Exception('User not found!', 404);
            }

            $data = [
                'lock_time' => $lockTime,
                'reason'    => $note,
                'user_id'   => $id,
                'banned_at'  => date('Y-m-d H:i:s'),
                'banned_by' => Auth::user()->id,
            ];

            $user->update([
                'status' => User::STAUS_KEY['BAN']
            ]);
            $user->bans()->updateOrCreate(
                ['user_id' => $user->id], // điều kiện tìm
                $data // dữ liệu cập nhật / tạo mới
            );

            return response()->json([
                'message' => 'Tài khoản '.$user->email.' đã bị ban '.User::UserBanKey[$lockTime],
            ]);

        }catch (\Exception $e){
            logger('Controller: UserController, Method: banAccount, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());
            return response()->json([
                'message' => 'Có lỗi xảy ra khi lấy user!',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function getAccountBan( $id)
    {
        try {
            $user = User::find($id);

            if(!$user){
                throw new \Exception('User not found!', 404);
            }

            return response()->json([
                'dataUser' => $user->bans()->first()
            ]);

        }catch (\Exception $e){
            logger('Controller: UserController, Method: getAccountBan, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());
            return response()->json([
                'message' => 'Có lỗi xảy ra khi lấy user!',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
