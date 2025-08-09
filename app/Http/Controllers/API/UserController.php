<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        return response()->json(User::select(['_id','name','email'])->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required','string','max:100'],
            // fuerza validador a usar mongodb:
            'email'    => ['required','email', Rule::unique('users','email')->where(fn($q)=>$q)->connection('mongodb')],
            'password' => ['required','string','min:6'],
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        return response()->json($user->only(['_id','name','email']), 201);
    }

    public function show(string $id)
    {
        $user = User::find($id);
        return $user
            ? response()->json($user->only(['_id','name','email']))
            : response()->json(['message'=>'Not found'], 404);
    }

    public function update(Request $request, string $id)
    {
        $user = User::find($id);
        if (!$user) return response()->json(['message'=>'Not found'], 404);

        $data = $request->validate([
            'name'  => ['sometimes','string','max:100'],
            'email' => [
                'sometimes','email',
                Rule::unique('users','email')->ignore($id, '_id')->connection('mongodb')
            ],
            'password' => ['sometimes','string','min:6'],
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);
        return response()->json($user->only(['_id','name','email']));
    }

    public function destroy(string $id)
    {
        $user = User::find($id);
        if (!$user) return response()->json(['message'=>'Not found'], 404);

        $user->delete();
        return response()->json(null, 204);
    }
    
}
