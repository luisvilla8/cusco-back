<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Util\Constants\TransactionsConstants;

class PurchasesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $purchases = TransactionController::getTransactionsByType(TransactionsConstants::TRANSACTION_TYPE_PURCHASE);
        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de compras",
            "data" => $purchases,
        ], 200);
    }

    public function getPurchases() {
        $purchases = TransactionController::getTransactions(TransactionsConstants::TRANSACTION_TYPE_PURCHASE);
        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de compras",
            "data" => $purchases,
        ], 200);
    }

    public function getPurchaseDetailsBySaleId(int $purchaseId)
    {
        if ($purchaseId !== TransactionsConstants::TRANSACTION_TYPE_PURCHASE) {
            return response()->json([
                "status" => 1,
                "msg" => "OK, lista de detalles de la compra",
                "data" => [],
            ], 200);
        }
        $purchaseDetails = TransactionDetailController::getTransactionDetailsByTransactionId($purchaseId);
        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de detalles de la compra",
            "data" => $purchaseDetails['details'],
        ], 200);
    }

    public function getPurchasesByProviderCategories(Request $request)
    {
        $categoriesParam = $request->categories;
        $sales = TransactionController::getTransactionsByAgentCategories($categoriesParam, TransactionsConstants::TRANSACTION_TYPE_PURCHASE);

        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de compras",
            "data" => $sales,
        ], 200);
    }

    public function getPurchasesDebtsByProviderCategories(Request $request)
    {
        $categoriesParam = $request->categories;

        $sales = DebtsController::getDebtsByAgentCategories($categoriesParam, TransactionsConstants::AGENT_TYPE_PROVIDER);

        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de deudas",
            "data" => $sales,
        ], 200);
    }

    public function savePurchase(Request $request)
    {
        $transaction = TransactionController::saveTransaction($request);
        return response()->json([
            "status" => 201,
            "msg" => "¡Registro de compra exitoso!",
            "data" => $transaction->id
        ], 200);
    }
}
