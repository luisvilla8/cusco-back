<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TransactionType\IndexTransactionTypeRequest;
use App\Services\TransactionTypeService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class TransactionTypeController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private TransactionTypeService $transactionTypeService
    ) {}

    /**
     * Obtener todos los tipos de transacción con paginación
     */
    public function index(IndexTransactionTypeRequest $request): JsonResponse
    {
        $result = $this->transactionTypeService->getAllTransactionTypes($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Obtener un tipo de transacción específico
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->transactionTypeService->getTransactionType($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Obtener lista para dropdowns
     */
    public function list(): JsonResponse
    {
        $result = $this->transactionTypeService->getTransactionTypesList();
        return $this->handleServiceResult($result);
    }
}