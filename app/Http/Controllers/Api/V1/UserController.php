<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = User::all();
        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de usuarios",
            "data" => $users
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Usuario  $usuario
     * @return \Illuminate\Http\Response
     */
    public function show(Usuario $usuario)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Usuario  $usuario
     * @return \Illuminate\Http\Response
     */
    public function edit(Usuario $usuario)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        $user->nombre = $request->nombre ?? $user->nombre;
        $user->apellidos = $request->apellidos ?? $user->apellidos;
        $user->email = $request->email ?? $user->email;
        $user->password = $request->password ?? $user->password;
        $user->id_tipo_usuario = $request->id_tipo_usuario ?? $user->id_tipo_usuario;
        $user->telefono = $request->telefono ?? $user->telefono;
        $user->save();

        if ($user) {
            return response()->json([
                "msg" => 'Usuario actualizado satisfactoriamente',
                "data" => $user
            ], 200);
        }

        return response()->json([
            "msg" => 'Usuario no encontrado',
        ], 404);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = User::find($id);
        if ($user) {
            $user->delete();
            return response()->json([
                "msg" => 'Usuario eliminado satisfactoriamente'
            ], 200);
        }
        return response()->json([
            "msg" => 'Usuario no encontrado',
        ], 404);
    }
}
