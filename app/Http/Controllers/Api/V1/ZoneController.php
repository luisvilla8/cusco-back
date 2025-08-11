<?php

namespace App\Http\Controllers\Api\V1;

use App\Attributes\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Zone\{StoreZoneRequest, UpdateZoneRequest, IndexZoneRequest};
use App\Services\ZoneService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;



#[Role(['Administrador'])]
class ZoneController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ZoneService $zoneService
    ) {}

    /**
     * Get all zones with pagination
     */
    #[Role(['Administrador', 'Vendedor'], 'Solo administradores y vendedores pueden ver la lista de zonas')]
    public function index(IndexZoneRequest $request): JsonResponse
    {
        $result = $this->zoneService->getAllZones($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Get single zone
     */

    #[Role(['Administrador', 'Vendedor'])]
    public function show(int $id): JsonResponse
    {
        $result = $this->zoneService->getZone($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Create new zone
     */
    public function store(StoreZoneRequest $request): JsonResponse
    {
        $result = $this->zoneService->createZone($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Update existing zone
     */
    public function update(UpdateZoneRequest $request, int $id): JsonResponse
    {
        $result = $this->zoneService->updateZone($id, $request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Soft delete zone
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->zoneService->deleteZone($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Force delete zone
     */
    public function forceDelete(int $id): JsonResponse
    {
        $result = $this->zoneService->forceDeleteZone($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Get zones list for dropdown
     */

    #[Role(['Administrador', 'Vendedor'], 'Solo administradores y vendedores pueden ver la lista de zonas')]
    public function list(): JsonResponse
    {
        $result = $this->zoneService->getZonesList();
        return $this->handleServiceResult($result);
    }
}
