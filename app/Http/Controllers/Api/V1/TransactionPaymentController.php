<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Models\TransactionPayment;

class TransactionPaymentController extends Controller
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
            'id_transaccion' => 'required|integer',
            'monto_pagado' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->getMessages(), 400);
        }

        $payment = TransactionPayment::create([
            'id_transaccion' => $request->id_transaccion,
            'monto_pagado' => $request->monto_pagado,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $payment->save();
        return response()->json([
            "status" => 1,
            "msg" => "¡Registro de pago exitoso!",
            "data" => $payment
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
