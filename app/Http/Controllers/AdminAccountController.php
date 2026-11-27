<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

/** Kelola akun Admin: HANYA Owner. Hanya menyentuh akun ber-role admin. */
class AdminAccountController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => User::where('role', 'admin')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:150|unique:users,email',
            'phone'    => 'required|string|max:20|unique:users,phone',
            'password' => 'required|string|min:8|max:100',
        ]);

        $admin = User::create($data + ['role' => 'admin', 'is_active' => true]);

        return response()->json(['success' => true, 'message' => 'Akun admin dibuat.', 'data' => $admin], 201);
    }

    public function update(Request $request, int $id)
    {
        $admin = User::where('role', 'admin')->findOrFail($id);

        $data = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'phone'     => 'sometimes|string|max:20|unique:users,phone,'.$admin->id,
            'password'  => 'sometimes|string|min:8|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $admin->update($data);

        return response()->json(['success' => true, 'message' => 'Akun admin diperbarui.', 'data' => $admin]);
    }

    public function destroy(int $id)
    {
        User::where('role', 'admin')->findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Akun admin dihapus.']);
    }
}
