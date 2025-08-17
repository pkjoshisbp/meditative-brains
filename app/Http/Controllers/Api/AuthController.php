<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string'
        ]);

        $field = str_contains($credentials['username'],'@') ? 'email' : 'name';
        $user = User::where($field, $credentials['username'])->first();
        if(!$user || !Hash::check($credentials['password'], $user->password)){
            return response()->json(['message'=>'Invalid credentials'], 401);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'userId' => $user->id,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }

    public function user(Request $request)
    {
        $u = $request->user();
        return ['id'=>$u->id,'name'=>$u->name,'email'=>$u->email];
    }
}
