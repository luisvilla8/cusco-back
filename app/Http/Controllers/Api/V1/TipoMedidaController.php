<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TipoMedida;
use Illuminate\Http\Request;

class TipoMedidaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    public function list()
    {
        $tipos = TipoMedida::all();
        return response()->json([
            "status" => 1,
            "msg" => "¡Lista de tipos de medida!",
            "data" => $tipos
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
     * @param  \App\Models\TipoMedida  $tipoMedida
     * @return \Illuminate\Http\Response
     */
    public function show(TipoMedida $tipoMedida)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\TipoMedida  $tipoMedida
     * @return \Illuminate\Http\Response
     */
    public function edit(TipoMedida $tipoMedida)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\TipoMedida  $tipoMedida
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, TipoMedida $tipoMedida)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\TipoMedida  $tipoMedida
     * @return \Illuminate\Http\Response
     */
    public function destroy(TipoMedida $tipoMedida)
    {
        //
    }
}
