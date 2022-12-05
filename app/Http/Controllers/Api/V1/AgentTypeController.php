<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AgentType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;

class AgentTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $agentTypes = AgentType::all();
        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de tipos de agentes",
            "data" => $agentTypes,
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
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->getMessages(), 400);
        }

        $agentType = AgentType::create([
            "nombre" => $request->nombre,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $agentType->save();
        return response()->json([
            "status" => 1,
            "msg" => "¡Registro de tipo de agente exitoso!",
            "data" => $agentType
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
        $agentType = AgentType::find($id);
        if ($agentType) {
            return response()->json([
                "msg" => 'Tipo de agente encontrado',
                "data" => $agentType
            ], 200);
        }

        return response()->json([
            "msg" => 'Tipo de agente no encontrado',
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
