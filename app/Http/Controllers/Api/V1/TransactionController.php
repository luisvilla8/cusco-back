<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Agent;
use App\Models\Product;
use App\Models\TransactionType;
use Validator;
use App\Http\Controllers\Api\V1\DebtsController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\TransactionDetailController;
use App\Http\Controllers\Api\V1\TransactionPaymentController;
use App\Util\TransactionHelper;
use App\Util\Constants\TransactionsConstants;
use App\Adapters\TransactionAdapter;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(string $transactionType = null)
    {
        if ($transactionType == null) {
            return response()->json([
                "status" => 1,
                "msg" => "OK, lista de transacciones",
                "data" => Transaction::all(),
            ], 200);
        }
        return Transaction::where('transaction_type_id', (int)$transactionType)->get();
    }

    public static function getTransactions(string $transactionType) {
        $transactions = Transaction::select(
                'transactions.id',
                'agents.name as agent_name',
                'agents.categories as agent_categories',
                'transactions.amount_paid',
                'transactions.transaction_type_id',
                'transactions.created_at',
                'transactions.updated_at'
            )
            ->join('agents', 'agents.id', '=', 'transactions.agent_id')
            ->leftJoin('transaction_details', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->groupBy(
                'transactions.id',
                'agents.name',
                'agents.categories',
                'transactions.amount_paid',
                'transactions.transaction_type_id',
                'transactions.created_at',
                'transactions.updated_at'
            )
            ->selectRaw('SUM(transaction_details.price * transaction_details.quantity) as total_price')
            ->get();

        $transactions->transform(function ($transaction) {
            $transaction->agent_categories = json_decode($transaction->agent_categories);
            $transaction->amount_due = $transaction->total_price - $transaction->amount_paid;
            return $transaction;
        });

        return TransactionHelper::filterTransactionsByType(collect($transactions->toArray()), $transactionType);
    }

    public static function getTransactionsByType(string $transactionType)
    {
        return Transaction::where('transaction_type_id', (int)$transactionType)->get();
    }

    public static function getTransactionsByAgentCategories(string $categoriesParam, int $transactionTypeId)
    {
        $categories = explode(',', $categoriesParam);

        return Transaction::where([['transaction_type_id', '=', $transactionTypeId]])
            ->whereHas('agent', fn ($query) => $query->whereJsonContains('categories', $categories))
            ->get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public static function saveTransaction(Request $request)
    {
        $transactionAgent = Agent::find($request->agent_id);

        $transactionTypeId = TransactionType::getTransactionTypeByAgentType($transactionAgent->agent_type_id)->id;

        $transaction = TransactionController::createTransaction($request, $transactionTypeId);

        $updatedTransationItems = TransactionController::saveTransactionItems($request->all()['items'], $transaction->id);

        // $updatedTransactionPayments = TransactionController::saveTransactionPayments($transaction);

        // DebtsController::saveAgentDebt($transactionAgent->id, $transactionTotalAmount);
        
        // ProductController::updateProductsAfterTransaction($productsWithUpdatedQuantityByTransactionType);

        $transactionWithItems = [
            'transaction' => $transaction,
            'items' => $updatedTransationItems,
        ];

        return $transactionWithItems;
    }

    public static function createTransaction(Request $request, int $transactionTypeId)
    {
        $validator = Validator::make($request->all(), [
            "agent_id" => "required|integer",
            "items" => "required|array",
            "amount_paid" => "required|numeric",
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->getMessages(), 400);
        }

        return Transaction::create([
            "agent_id" => $request->agent_id,
            "transaction_type_id" => $transactionTypeId,
            "amount_paid" => $request->amount_paid,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public static function updateTransaction(Request $updatedTransaction, int $transactionId)
    {
        $transaction = Transaction::find($transactionId);

        if (!$transaction) {
            return response()->json([
                "status" => 0,
                "msg" => "Transacción no encontrada",
            ], 404);
        }

        $updatedTransationItems = TransactionController::saveTransactionItems($updatedTransaction->all()['items'], $updatedTransaction->id);

        $updatedTransactionPayments = TransactionController::saveTransactionPayments($updatedTransaction);

        $transaction->update([
            "agent_id" => $updatedTransaction->agent_id,
            "amount_paid" => $updatedTransaction->amount_paid,
            'updated_at' => now()
        ]);

        return response()->json([
            "status" => 1,
            "msg" => "¡Transacción actualizada exitosamente!",
            "data" => $updatedTransactionPayments,
        ], 200);
    }

    public static function getItemWithPricesByTransactionType(array $items, int $transactionTypeId)
    {
        $itemsCollection = collect($items);

        return $itemsCollection->map(function ($item) use ($transactionTypeId) {
            $itemAmount = $transactionTypeId === TransactionsConstants::TRANSACTION_TYPE_SALE ? $item['price'] : $item['cost'];
            return array_merge($item, ['finalPrice' => $itemAmount * $item['quantity']]);
        });
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

    public static function saveTransactionPayments($updatedTransaction)
    {
        $updatedTransactionPayments = $updatedTransaction->all()['payments'] ?? [];
        $transactionId = $updatedTransaction['id'] ?? null;

        foreach ($updatedTransactionPayments as $item) {
            $item['transaction_id'] = $transactionId;

            TransactionPaymentController::updateTransactionPaymentById($item['id'], $item);
        }
    }

    public static function saveTransactionItems($items, $transactionId)
    {
        // foreach ($items as $item) {
        //     TransactionController::saveTransactionItem($item, $transactionId);
        // }
        $updatedItems = [];

        foreach ($items as $item) {
            $item['transaction_id'] = $transactionId;
            $updatedItem = TransactionController::saveTransactionItem($item, $transactionId)->data;
            if ($updatedItem) {
                $updatedItems[] = $updatedItem;
            }
        }

        return $updatedItems;
    }

    public static function saveTransactionItem($item, $transactionId)
    {
        if (isset($item['id']) && $item['id'] !== null) {
            return TransactionController::updateTransactionItem($transactionId, $item);
        }

        if (isset($item['status']) && $item['status'] === TransactionsConstants::TRANSACTION_ITEM_STATUS_DELETED) {
            return TransactionController::deleteTransactionItem($item, $transactionId);
        }


        $itemRequest = new Request();
        $itemRequest->setMethod('POST');
        $itemRequest->request->add([
            'product_id'     => $item['product_id'],
            'transaction_id' => $transactionId,
            'cost'           => $item['cost'],
            'price'          => $item['price'],
            'quantity'       => $item['quantity'],
        ]);

        $response = (new TransactionDetailController)->store($itemRequest)->getData();

        return $response;
    }

    public static function deleteTransactionItem($item, $transactionId)
    {
        $itemRequest = new Request();
        $itemRequest->setMethod('DELETE');
        $itemRequest->request->add([
            'transaction_id' => $transactionId,
            'product_id'     => $item['product_id'],
        ]);

        return (new TransactionDetailController)->destroy($itemRequest);
    }

    public static function udpateTransactionItem($transactionId, $item)
    {
        $itemRequest = new Request();
        $itemRequest->setMethod('PUT');
        $itemRequest->request->add([
            'product_id'     => $item['product_id'],
            'transaction_id' => $transactionId,
            'cost'           => $item['cost'],
            'price'          => $item['price'],
            'quantity'       => $item['quantity'],
            'id'             => $item['id'],
        ]);

        return (new TransactionDetailController)->update($itemRequest, $itemId)->getData()->data->id ?? null;
    }

    public static function saveTransactionPaymentAndUpdateDebt(Request $request)
    {
        if ($request->amount_paid <= 0) {
            return null;
        }
    
        $paymentRequest = new Request();
        $paymentRequest->setMethod('POST');
        $paymentRequest->request->add([
            'transaction_id' => $request->transaction_id,
            'amount_paid' => $request->amount_paid,
        ]);

        $transactionPayment = (new TransactionPaymentController)->store($paymentRequest);
        DebtsController::saveAgentDebt($request->agent_id, -abs($request->amount_paid));
        return $transactionPayment;
    }

    public static function saveTransactionPaymentAndUpdateDebtByRoute(Request $request)
    {
        $transactionPayment = TransactionController::saveTransactionPaymentAndUpdateDebt($request);
        return response()->json([
            "status" => 1,
            "msg" => "¡Registro de pago exitoso!",
            "data" => $transactionPayment
        ], 201);
    }
}
