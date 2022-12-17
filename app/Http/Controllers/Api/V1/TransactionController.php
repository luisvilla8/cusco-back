<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Agent;
use App\Models\TransactionType;
use Validator;
use App\Http\Controllers\Api\V1\TransactionDetailController;
use App\Http\Controllers\Api\V1\TransactionPaymentController;
use App\Models\Producto;

class TransactionController extends Controller
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
            "id_agente" => "required|integer",
            "items" => "required|array",
            "monto_pagado" => "required|numeric",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->getMessages(), 400);
        }

        $agent = Agent::find($request->id_agente);
        $transactionType = TransactionType::getTransactionTypeByAgentType($request->id_agente);
        foreach ($request->items as $item) {
            TransactionController::saveTransactionDetail($item, $transactionType);
        }
        $transaction = Transaction::create([
            "id_agente" => $agent->id,
            "id_tipo_transaccion" => $transactionType->id,
            "monto_pagado" => $request->monto_pagado ?? 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $transaction->save();
        
        TransactionController::saveTransactionPayment($transaction->id, $request->monto_pagado);
        return response()->json([
            "status" => 1,
            "msg" => "¡Registro de transaction exitoso!",
            "data" => $transaction->id
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

    public function saveTransactionDetail($item, $transactionType)
    {
        $itemRequest = new Request();
        $itemRequest->setMethod('POST');
        $product = Producto::find($item['id_producto']);
        if ($product) {
            $itemRequest->request->add([
                'id_producto' => $item['id_producto'],
                'id_transaccion' => $transactionType->id,
                'costo' => $product->costo,
                'cantidad' => $item['cantidad'],
            ]);
            $response = ((new TransactionDetailController)->store($itemRequest))->getData();
            $transactionDetailId = $response->data->id;
            return $transactionDetailId;
        }
    }
    public function saveTransactionPayment($transactionId, $payment)
    {
        $paymentRequest = new Request();
        $paymentRequest->setMethod('POST');
        $paymentRequest->request->add([
            'id_transaccion' => $transactionId,
            'monto_pagado' => $payment,
        ]);
        return (new TransactionPaymentController)->store($paymentRequest);
    }
}
