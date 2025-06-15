<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function createUser(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'nullable|string|min:6', // có thể null
            'phone'    => 'required|string',
            'address'  => 'nullable|string',
            'note'     => 'nullable|string',
            'status'   => 'required|integer',
            'role'     => 'required|integer',
        ]);

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
}
