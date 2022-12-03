<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Agent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;

class AgentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->type ? $request->type : "";
        if (!$type) {
            $agents = Agent::all();
            return response()->json([
                "status" => 1,
                "msg" => "OK, lista de agentes",
                "data" => $agents,
            ], 200);
        }
        $agents = Agent::where('id_tipo_agente', (int)$type)->get();
        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de agentes",
            "data" => $agents,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "nombre" => "required|string|max:255",
            "telefono" => "required|string|min:9|max:9",
            "direccion" => "required|string",
            "email" => "required|string|email|max:255|unique:agents",
            "dni" => "required|string|min:8|max:8",
            "ruc" => "required|string|min:11|max:11",
            "id_tipo_agente" => "required|integer"
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->getMessages(), 400);
        }

        $agent = Agent::create([
            "nombre" => $request->nombre,
            "telefono" => $request->telefono,
            "direccion" => $request->direccion,
            "email" => $request->email,
            "dni" => $request->dni,
            "ruc" => $request->ruc,
            "id_tipo_agente" => $request->id_tipo_agente,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $agent->save();
        return response()->json([
            "status" => 1,
            "msg" => "¡Registro de agente exitoso!",
            "data" => $agent
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $agent = Agent::find($id);
        if ($agent) {
            return response()->json([
                "msg" => 'Agente encontrado',
                "data" => $agent
            ], 200);
        }

        return response()->json([
            "msg" => 'Agente no encontrado',
        ], 404);
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
        $agent = Agent::find($id);
        $agent->nombre = $request->nombre ?? $agent->nombre;
        $agent->telefono = $request->telefono ?? $agent->telefono;
        $agent->direccion = $request->direccion ?? $agent->direccion;
        $agent->email = $request->email ?? $agent->email;
        $agent->dni = $request->dni ?? $agent->dni;
        $agent->ruc = $request->ruc ?? $agent->ruc;
        $agent->id_tipo_agente = $request->id_tipo_agente ?? $agent->id_tipo_agente;
        $agent->save();

        if ($agent) {
            return response()->json([
                "msg" => 'Agente actualizado satisfactoriamente',
                "data" => $agent
            ], 200);
        }

        return response()->json([
            "msg" => 'Agente no encontrado',
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
        $agent = Agent::find($id);
        if ($agent) {
            $agent->delete();
            return response()->json([
                "msg" => 'Agente eliminado satisfactoriamente'
            ], 200);
        }
        return response()->json([
            "msg" => 'Agente no encontrado',
        ], 404);
    }
}
