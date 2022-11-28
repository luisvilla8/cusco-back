<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Validator;
use Illuminate\Support\Facades\Hash;

use App\Models\User;
use App\Http\Controllers\Controller;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'telefono' => 'required|string|max:9',
            'email' => 'required|string|email|unique:usuarios',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        $user = User::create([
            'nombre' => $request->nombre,
            'apellidos' => $request->apellidos,
            'id_tipo_usuario' => 2,
            'telefono' => $request->telefono,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario creado satisfactoriamente!',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        $email = $request->email;
        $password = $request->password;

        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'password' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->getMessages(), 400);
        }

        $user = User::where("email", $email)->firstOrFail();
        if (!$user) {
            return response()->json([
                'message' => 'Este usuario no existe'
            ], 404);
        }
        if (!Hash::check($password, $user->password)) {
            return response()->json([
                'message' => 'Contraseña incorrecta'
            ], 404);
        }

        $token = $user->createToken("auth_token")->plainTextToken;
        $message = 'Hola ' . $user->nombre . ', usuario logueado satisfactoriamente!';
        
        return response()->json([
            'message' => $message,
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 201);
    }

    public function logout()
    {
        auth()->user()->tokens()->delete();

        return response()->json([
            'message' => 'Usuario deslogueado satisfactoriamente!'
        ], 200);
    }
}
