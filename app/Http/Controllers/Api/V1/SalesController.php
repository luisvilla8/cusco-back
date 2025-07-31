<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\TransactionDetailController;
use App\Http\Controllers\Api\V1\DebtsController;
use App\Util\Constants\TransactionsConstants;

class SalesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $sales = TransactionController::getTransactionsByType(TransactionsConstants::TRANSACTION_TYPE_SALE);
        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de ventas",
            "data" => $sales,
        ], 200);
    }
    
    public function getSales() {
        $sales = TransactionController::getTransactions(TransactionsConstants::TRANSACTION_TYPE_SALE);
        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de ventas",
            "data" => $sales,
        ], 200);
    }

    public function getSaleDetailsBySaleId(int $saleId)
    {
        $saleDetails = TransactionDetailController::getTransactionDetailsByTransactionId($saleId);

        if ($saleDetails['transaction']['transaction_type_id'] !== TransactionsConstants::TRANSACTION_TYPE_SALE) {
            return response()->json([
                "status" => 1,
                "msg" => "La transacción no es una venta",
                "data" => $saleDetails['transaction']['transaction_type_id'],
            ], 400);
        }

        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de detalles de la venta",
            "data" => $saleDetails['transaction'],
        ], 200);
    }

    public function getSalesByClientCategories(Request $request)
    {
        $categoriesParam = $request->categories;

        $sales = TransactionController::getTransactionsByAgentCategories($categoriesParam, TransactionsConstants::TRANSACTION_TYPE_SALE);

        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de ventas",
            "data" => $sales,
        ], 200);
    }

    public function getSalesDebtsByClientCategories(Request $request)
    {
        $categoriesParam = $request->categories;

        $sales = DebtsController::getDebtsByAgentCategories($categoriesParam, TransactionsConstants::AGENT_TYPE_CLIENT);

        return response()->json([
            "status" => 1,
            "msg" => "OK, lista de deudas",
            "data" => $sales,
        ], 200);
    }

    public function saveSale(Request $request)
    {
        $transaction = TransactionController::saveTransaction($request);
        return response()->json([
            "status" => 201,
            "msg" => "¡Registro de venta exitoso!",
            // "data" => $transaction->id
            "data" => $transaction
        ], 200);
    }

    public function updateSale(Request $request, $id)
    {
        return TransactionController::updateTransaction($request, $id);
    }
}
