<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function getUser(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
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

        // Paginate
        $data = $users->paginate($perPage);
        return response()->json([
            'data' => $data->items(),
            'total' => $data->total(),
            'per_page' => $data->perPage(),
            'current_page' => $data->currentPage(),
        ]);
    }
}
