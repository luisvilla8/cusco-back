<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Controllers\Api\V1\MeasureTypeController.php

namespace App\Http\Controllers\Api\V1;

use App\Attributes\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MeasureType\{StoreMeasureTypeRequest, UpdateMeasureTypeRequest, IndexMeasureTypeRequest};
use App\Services\MeasureTypeService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;


#[Role(['Administrador'])]

class MeasureTypeController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private MeasureTypeService $measureTypeService
    ) {}

    /**
     * Get all measure types with pagination
     */
    #[Role(['Administrador', 'Vendedor'])]

    public function index(IndexMeasureTypeRequest $request): JsonResponse
    {
        $result = $this->measureTypeService->getAllMeasureTypes($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Get single measure type
     */
    #[Role(['Administrador', 'Vendedor'])]

    public function show(int $id): JsonResponse
    {
        $result = $this->measureTypeService->getMeasureType($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Create new measure type
     */
    public function store(StoreMeasureTypeRequest $request): JsonResponse
    {
        $result = $this->measureTypeService->createMeasureType($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Update existing measure type
     */
    public function update(UpdateMeasureTypeRequest $request, int $id): JsonResponse
    {
        $result = $this->measureTypeService->updateMeasureType($id, $request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Soft delete measure type
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->measureTypeService->deleteMeasureType($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Force delete measure type
     */
    public function forceDelete(int $id): JsonResponse
    {
        $result = $this->measureTypeService->forceDeleteMeasureType($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Get measure types list for dropdown
     */
    #[Role(['Administrador', 'Vendedor'])]

    public function list(): JsonResponse
    {
        $result = $this->measureTypeService->getMeasureTypesList();
        return $this->handleServiceResult($result);
    }
}
