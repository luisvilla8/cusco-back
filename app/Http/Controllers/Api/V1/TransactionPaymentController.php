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
    public static function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|integer',
            'amount_paid' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->getMessages(), 400);
        }

        $payment = TransactionPayment::create([
            'transaction_id' => $request->transaction_id,
            'amount_paid' => $request->amount_paid,
            'payment_method_id' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $payment->save();
        return $payment;
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

    public static function getTransactionPaymentsByTransactionId($transactionId)
    {
        $transactionPayments = TransactionPayment::where('transaction_id', (int)$transactionId)->get();

        if ($transactionPayments) return $transactionPayments;

        return null;
    }

    public static function updateTransactionPaymentById($transactionPaymentId, $data)
    {
        $transactionPayment = TransactionPayment::find($transactionPaymentId);

        if (!$transactionPayment) {
            $paymentRequest = new Request();
            $paymentRequest->setMethod('POST');
            $paymentRequest->request->add([
                'transaction_id' => $data['transaction_id'],
                'amount_paid' => $data['amount_paid'],
            ]);

            return self::store($paymentRequest);
        }

        $transactionPayment->amount_paid = $data['amount_paid'] ?? $transactionPayment->amount_paid;
        $transactionPayment->save();

        return $transactionPayment;
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
