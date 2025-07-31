<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Traits\ApiResponseTrait.php

namespace App\Traits;

use App\Helpers\ResponseHelper;
use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * ✅ ÚNICO MÉTODO NECESARIO
     */
    protected function handleServiceResult(array $result): JsonResponse
    {
        return ResponseHelper::toJsonResponse($result);
    }

    // ❌ ELIMINAR: Todos los wrappers innecesarios
    // Si necesitas usar success/error directamente, usa ResponseHelper::successJson()
}