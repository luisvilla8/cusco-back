<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Controllers\Api\V1\TransactionController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transaction\{StoreTransactionRequest, UpdateTransactionRequest, IndexTransactionRequest, AddPaymentRequest, StoreReturnRequest};
use App\Services\TransactionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private TransactionService $transactionService
    ) {}

    public function index(IndexTransactionRequest $request): JsonResponse
    {
        $result = $this->transactionService->getAllTransactions($request->validated());
        return $this->handleServiceResult($result);
    }

    public function show(int $id): JsonResponse
    {
        $result = $this->transactionService->getTransaction($id);
        return $this->handleServiceResult($result);
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $result = $this->transactionService->createTransaction($request->validated());
        return $this->handleServiceResult($result);
    }

    public function update(UpdateTransactionRequest $request, int $id): JsonResponse
    {
        $result = $this->transactionService->updateTransaction($id, $request->validated());
        return $this->handleServiceResult($result);
    }

    public function destroy(int $id): JsonResponse
    {
        $result = $this->transactionService->deleteTransaction($id);
        return $this->handleServiceResult($result);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $result = $this->transactionService->forceDeleteTransaction($id);
        return $this->handleServiceResult($result);
    }

    public function list(): JsonResponse
    {
        $result = $this->transactionService->getTransactionsList();
        return $this->handleServiceResult($result);
    }

    // ✅ RUTAS ESPECIALIZADAS VERIFICADAS
    public function sales(IndexTransactionRequest $request): JsonResponse
    {
        // Solo ventas ENTREGADAS o DEVUELTAS
        $result = $this->transactionService->getSales($request->validated());
        return $this->handleServiceResult($result);
    }

    public function purchases(IndexTransactionRequest $request): JsonResponse
    {
        // Solo compras ENTREGADAS o DEVUELTAS (RECIBIDAS)
        $result = $this->transactionService->getPurchases($request->validated());
        return $this->handleServiceResult($result);
    }

    public function pendingDelivery(IndexTransactionRequest $request): JsonResponse
    {
        $result = $this->transactionService->getPendingDelivery($request->validated());
        return $this->handleServiceResult($result);
    }

    public function pendingPayment(IndexTransactionRequest $request): JsonResponse
    {
        $result = $this->transactionService->getPendingPayment($request->validated());
        return $this->handleServiceResult($result);
    }

    public function completed(IndexTransactionRequest $request): JsonResponse
    {
        $result = $this->transactionService->getCompleted($request->validated());
        return $this->handleServiceResult($result);
    }

    // ✅ ACCIONES ESPECÍFICAS
    // ✅ MARCAR COMO ENTREGADO SIN NOTAS
    public function markAsDelivered(int $id): JsonResponse
    {
        $result = $this->transactionService->markAsDelivered($id);
        return $this->handleServiceResult($result);
    }

    // ✅ MARCAR COMO DEVUELTO SIN NOTAS  
    public function markAsReturned(int $id): JsonResponse
    {
        $result = $this->transactionService->markAsReturned($id);
        return $this->handleServiceResult($result);
    }

    // ✅ CANCELAR SIN NOTAS
    public function cancelTransaction(int $id): JsonResponse
    {
        $result = $this->transactionService->cancelTransaction($id);
        return $this->handleServiceResult($result);
    }

    public function addPayment(AddPaymentRequest $request, int $id): JsonResponse
    {
        $result = $this->transactionService->addPayment($id, $request->validated());
        return $this->handleServiceResult($result);
    }

    public function dependencies(int $id): JsonResponse
    {
        $result = $this->transactionService->getTransactionDependencies($id);
        return $this->handleServiceResult($result);
    }

    // Método para crear devoluciones
    public function createReturn(StoreReturnRequest $request): JsonResponse
    {
        $result = $this->transactionService->createReturn($request->validated());
        return $this->handleServiceResult($result);
    }
}
