<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TransactionDetail;
use Validator;

class TransactionDetailController extends Controller
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "id_producto" => "required|integer",
            "id_transaccion" => "required|integer",
            "costo" => "required|numeric",
            "cantidad" => "required|numeric",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->getMessages(), 400);
        }

        $transactionDetail = TransactionDetail::create([
            "id_producto" => $request->id_producto,
            "id_transaccion" => $request->id_transaccion,
            "costo" => $request->costo,
            "cantidad" => $request->cantidad,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $transactionDetail->save();
        return response()->json([
            "status" => 1,
            "msg" => "¡Registro de detalle de transaccion exitoso!",
            "data" => $transactionDetail
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
