<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Controllers\Api\V1\TripController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Trip\{IndexTripRequest, StoreTripRequest, UpdateTripRequest};
use App\Services\TripService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use App\Models\Trip; // Asegúrate de tener el modelo Trip importado

class TripController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private TripService $tripService
    ) {}

    /**
     * ✅ LISTAR TRIPS (con filtros y permisos)
     */
    public function index(IndexTripRequest $request): JsonResponse
    {
        $result = $this->tripService->getAllTrips($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ MOSTRAR TRIP ESPECÍFICO (con permisos)
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->tripService->getTrip($id);
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ CREAR TRIP
     */
    public function store(StoreTripRequest $request): JsonResponse
    {
        $result = $this->tripService->createTrip($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ ACTUALIZAR TRIP (con permisos)
     */
    public function update(UpdateTripRequest $request, int $id): JsonResponse
    {
        $result = $this->tripService->updateTrip($id, $request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ ELIMINAR TRIP (con permisos)
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->tripService->deleteTrip($id);
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ ELIMINAR TRIP PERMANENTEMENTE (con permisos)
     */
    public function forceDelete(int $id): JsonResponse
    {
        $result = $this->tripService->forceDeleteTrip($id);
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ LISTA PARA DROPDOWNS (con permisos)
     */
    public function list(): JsonResponse
    {
        $result = $this->tripService->getTripsList();
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ NUEVO: DIAGNOSTICAR DEPENDENCIAS DE UN TRIP
     */
    public function dependencies(int $id): JsonResponse
    {
        try {
            $trip = Trip::withTrashed()->with(['transactions', 'egresses'])->find($id);
            
            if (!$trip) {
                return response()->json([
                    'success' => false,
                    'message' => 'Viaje no encontrado'
                ], 404);
            }
            
            $dependencies = $trip->getDependenciesDetails();
            $canDelete = $trip->canBeDeleted();
            
            return response()->json([
                'success' => true,
                'message' => 'Dependencias del viaje obtenidas',
                'data' => [
                    'trip' => [
                        'id' => $trip->id,
                        'name' => $trip->name,
                        'code' => $trip->code,
                        'deleted_at' => $trip->deleted_at
                    ],
                    'can_be_deleted' => $canDelete,
                    'dependencies' => $dependencies
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener dependencias: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ TEMPORAL: Force delete sin validaciones - CORREGIDO
     */
    public function forceDeleteDirect(int $id): JsonResponse
    {
        try {
            $trip = Trip::withTrashed()->find($id);
            
            if (!$trip) {
                return response()->json([
                    'success' => false,
                    'message' => 'Viaje no encontrado'
                ], 404);
            }
            
            $tripName = $trip->name;
            $success = $trip->forceDeleteWithoutValidations();
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => "Viaje '{$tripName}' eliminado permanentemente (método directo)",
                    'data' => [
                        'id' => $id,
                        'name' => $tripName,
                        'deleted' => true
                    ]
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al eliminar el viaje'
                ], 500);
            }
            
        } catch (\Exception $e) {
            \Log::error('Error in direct force delete', [
                'trip_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
}