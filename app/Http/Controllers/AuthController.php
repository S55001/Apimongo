<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $req)
    {
        $req->validate([
            'email'    => ['required','email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $req->email)->first();

        if (!$user || !Hash::check($req->password, $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        // token simple (dev)
        $plain = Str::random(60);
        $user->api_token = hash('sha256', $plain);
        $user->save();

        return response()->json([
            'token' => $plain,
            'user'  => [
                '_id'   => (string)($user->_id ?? $user->id),
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role ?? 'customer',
            ],
        ]);
    }
}
