<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Mail\SendEmail;
use App\Models\User;
use App\Models\UserBan;
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
        $data = $users->get();


        return response()->json([
            'data' => $data,

        ]);
    }

    public function createUser(UserRequest $request)
    {

        try {
            $password = $request->filled('password') ? $request->input('password') : '12345678';

            $user = User::create([
                'name'     => $request['name'],
                'email'    => $request['email'],
                'password' => bcrypt($password),
                'status'   => $request['status'],
                'role'     => 1,
            ]);

            $user->profile()->create([
                'phone'   => $request['phone'],
                'address' => $request['address'] ?? '', // tránh lỗi nếu null
                'note'    => $request['note'] ?? '',    // tránh lỗi nếu null
            ]);

            if( $request->input('email_template')){
                $message = str_replace('{nameShop}', config('app.name'), $request->input('email_template')['subject']);
                $body = $request->input('email_template')['body'];
                $template = $request->input('email_template')['viewTemplate'];

                Mail::to($user->email)->send(new SendEmail($message, $body,$template));

            }

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

            // Kiểm tra nếu là user đang login
            if (Auth::id() == $user->id) {
                return response()->json([
                    'message' => 'Bạn không thể ban tài khoản chính mình.',
                ]);
            }

            $data = [
                'lock_time' => $lockTime,
                'reason'    => $note,
                'user_id'   => $id,
                'banned_at'  => date('Y-m-d H:i:s'),
                'banned_by' => Auth::user()->id,
                'status'    => UserBan::STAUS_KEY['BAN'],
            ];

            $user->update([
                'status' => User::STAUS_KEY['BAN']
            ]);
            $user->bans()->create($data);

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

            $ban = $user->bans()->first();

            return response()->json([
                'dataUser' => $ban
            ]);

        }catch (\Exception $e){
            logger('Controller: UserController, Method: getAccountBan, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());
            return response()->json([
                'message' => 'Có lỗi xảy ra khi lấy user!',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function deleteUser($id)
    {
        try {
            $user = User::find($id);

            if(!$user){
                throw new \Exception('User not found!', 404);
            }

            // Kiểm tra nếu là user đang login
            if (Auth::id() == $user->id) {
                return response()->json([
                    'message' => 'Bạn không thể xóa tài khoản đang đăng nhập.',
                ]);
            }

            $user->delete();

            return response()->json([
                'message' => 'Tài khoản '.$user->email.' đã xóa thành công.',
            ]);
        }catch (\Exception $e){
            logger('Controller: UserController, Method: deleteUser, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());
            return response()->json([
                'message' => 'Có lỗi xảy ra khi lấy user!',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function unBan($id)
    {
        try {
            $user = User::find($id);

            if(!$user){
                throw new \Exception('User not found!', 404);
            }

            $user->bans()->update([
                'unbanned_at'  => date('Y-m-d H:i:s'),
                'status' => UserBan::STAUS_KEY['UNBAN']
            ]);

            $user->update([
                'status' => User::STAUS_KEY['ACTIVE']
            ]);

            return response()->json([
                'message' => 'Tài khoản ' . $user->email . ' đã được mở khóa thành công.',
            ]);
        }catch (\Exception $e){
            logger('Controller: UserController, Method: unBan, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());
            return response()->json([
                'message' => 'Có lỗi xảy ra khi lấy user!',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function deleteMultiple($ids)
    {
        try {
            // 1. Kiểm tra nếu không có ID
            if (empty($ids)) {
                return response()->json([
                    'message' => 'Không có ID nào được cung cấp để xóa.'
                ], 400);
            }

            // 2. Chuyển chuỗi thành mảng số nguyên
            $idArray = array_filter(array_map('intval', explode(',', $ids)));

            if (empty($idArray)) {
                return response()->json([
                    'message' => 'Danh sách ID không hợp lệ.'
                ], 400);
            }

            // 3. Tìm các user có ID trong mảng và ID là chính mình
            $protectedUsers = User::whereIn('id', $idArray)
                ->where('id', Auth::id())
                ->get();

            // 4. Nếu có user bị chặn xóa (chính mình)
            if ($protectedUsers->isNotEmpty()) {
                $emails = $protectedUsers->pluck('email')->toArray();
                $emailList = implode(', ', $emails);

                throw new \Exception("Không thể xóa tài khoản đang đăng nhập: {$emailList}", 403);
            }

            // 5. Xóa các user còn lại
            $deletedCount = User::whereIn('id', $idArray)->delete();

            return response()->json([
                'message' => "Đã xóa thành công {$deletedCount} người dùng.",
                'deleted' => $deletedCount
            ]);
        } catch (\Exception $e) {
            logger('Controller: UserController, Method: deleteMultiple, Error: ' . $e->getMessage() . ', Line: ' . $e->getLine());

            return response()->json([
                'message' => 'Có lỗi xảy ra khi xóa user!',
                'error'   => $e->getMessage()
            ], $e->getCode() == 403 ? 403 : 500);
        }
    }

}
