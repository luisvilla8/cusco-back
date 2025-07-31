<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\TransactionPaymentController;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Validator;

use App\Util\TransactionHelper;

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

    public static function getTransactionDetailsByTransactionId(int $transactionId)
    {
        $transaction = Transaction::find($transactionId);

        if (!$transaction) {
            throw new \Exception("Transaction not found");
        }

        $transactionDetails = $transaction->details;

        $agentFound = AgentController::getAgentById($transaction->agent_id);

        $adaptedTransactionProducts = $transaction->details->map(function ($detail) {
            $product = ProductController::getProductById($detail->product_id);
    
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $detail->price,
                'cost' => $detail->cost,
                'quantity' =>  $detail->quantity,
                'stock' => $product->quantity,
                'total_price' => $detail->quantity * $detail->price
            ];
        });

        $transactionTotalCost = TransactionHelper::calculateTotalSumByField($adaptedTransactionProducts, 'cost', 'quantity');
        $transactionTotalPrice = TransactionHelper::calculateTotalSumByField($adaptedTransactionProducts, 'price', 'quantity');

        $transactionPayments = TransactionPaymentController::getTransactionPaymentsByTransactionId($transaction->id);

        $adaptedTransaction = [
            'id' => $transaction->id,
            'agent' => [
                'id' => $agentFound->id,
                'name' => $agentFound->name,
            ],
            'products' => $adaptedTransactionProducts,
            'amount_paid' => $transaction->amount_paid,
            'total_cost' => $transactionTotalCost,
            'total_price' => $transactionTotalPrice,
            'amount_due' => $transactionTotalPrice - $transaction->amount_paid,
            'payments' => $transactionPayments,
            'transaction_type_id' => $transaction->transaction_type_id,
        ];

        return [
            'transaction' => $adaptedTransaction,
            'details' => $transactionDetails,
        ];
    }

    public static function updateTransactionDetailById(int $id, array $data)
    {
        $transactionDetail = TransactionDetail::find($id);

        if (!$transactionDetail) {
            throw new \Exception("Transaction detail not found");
        }

        $transactionDetail->cost = $data['cost'] ?? $transactionDetail->cost;
        $transactionDetail->price = $data['price'] ?? $transactionDetail->price;
        $transactionDetail->quantity = $data['quantity'] ?? $transactionDetail->quantity;
        $transactionDetail->save();

        return $transactionDetail;
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
            "product_id" => "required|integer",
            "transaction_id" => "required|integer",
            "cost" => "required|numeric",
            "price" => "required|numeric",
            "quantity" => "required|numeric",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->getMessages(), 400);
        }

        $transactionDetail = TransactionDetail::create([
            "product_id" => $request->product_id,
            "transaction_id" => $request->transaction_id,
            "cost" => $request->cost,
            "price" => $request->price,
            "quantity" => $request->quantity,
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
