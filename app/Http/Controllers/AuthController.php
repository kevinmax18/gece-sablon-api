<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** Register hanya membuat akun PELANGGAN. Role tidak pernah diterima dari klien. */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:150|unique:users,email',
            'phone'    => 'required|string|max:20|unique:users,phone',
            'password' => 'required|string|min:8|max:100',
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'],
            'password'  => $data['password'], // di-hash otomatis (cast 'hashed')
            'role'      => 'customer',
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil.',
            'data'    => $this->payload($user),
        ], 201);
    }

    /** Login dengan email ATAU nomor HP. Role berasal dari server. */
    public function login(Request $request)
    {
        $data = $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['login'])->orWhere('phone', $data['login'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email/No. HP atau kata sandi salah.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => $this->payload($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logout berhasil.']);
    }

    public function me(Request $request)
    {
        return response()->json(['success' => true, 'data' => $request->user()]);
    }

    private function payload(User $user): array
    {
        return [
            'token' => $user->createToken('android', ['*'], now()->addDays(7))->plainTextToken,
            'user'  => $user,
        ];
    }
}
