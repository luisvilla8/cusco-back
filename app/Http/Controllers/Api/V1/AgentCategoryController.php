<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AgentCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Validator;

class AgentCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de categorías",
            "data" => AgentCategory::all(),
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
        $validator = Validator::make($request->all(), [
            "name" => "required|string|min:1",
            "type" => "required|string|min:1",
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->getMessages(), 400);
        }

        $agentCategories = AgentCategory::create([
            "name" => $request->name,
            "type" => $request->type,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $agentCategories->save();

        return response()->json([
            "status" => 1,
            "msg" => "¡Registro de categría exitoso!",
            "data" => $agentCategories
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
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        
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
        $agentCategories = AgentCategory::find($id);
        $agentCategories->name = $request->name ?? $agentCategories->name;
        $agentCategories->type = $request->type ?? $agentCategories->type;
        $agentCategories->save();

        if ($agentCategories) {
            return $agentCategories->data;
        }

        return [];
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
