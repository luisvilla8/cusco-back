<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Controllers\Api\V1\PaymentMethodController.php

namespace App\Http\Controllers\Api\V1;

use App\Attributes\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PaymentMethod\IndexPaymentMethodRequest;
use App\Services\PaymentMethodService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

#[Role(['Administrador', 'Vendedor'])]

class PaymentMethodController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PaymentMethodService $paymentMethodService
    ) {}

    /**
     * Obtener todos los métodos de pago con paginación
     */

    public function index(IndexPaymentMethodRequest $request): JsonResponse
    {
        $result = $this->paymentMethodService->getAllPaymentMethods($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Obtener un método de pago específico
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->paymentMethodService->getPaymentMethod($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Obtener lista para dropdowns
     */
    public function list(): JsonResponse
    {
        $result = $this->paymentMethodService->getPaymentMethodsList();
        return $this->handleServiceResult($result);
    }
}
