<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\TripService.php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\TripMapper;
use App\Models\Egress;
use App\Repositories\TripRepository;
use Illuminate\Support\Facades\Auth;

class TripService
{
    public function __construct(
        private TripRepository $tripRepository
    ) {}

    /**
     *  OBTENER TODOS LOS TRIPS (con permisos)
     */
    public function getAllTrips(array $filters = []): array
    {
        $user = Auth::user();

        $paginatedTrips = $this->tripRepository->getAllActiveWithPagination($filters, $user);
        $mapped = TripMapper::paginatedToDTOs($paginatedTrips);

        return ResponseHelper::paginated(
            $mapped['data'],
            $paginatedTrips,
            'Viajes obtenidos exitosamente'
        );
    }

    /**
     *  OBTENER TRIP ESPECÍFICO (con permisos)
     */
    public function getTrip(int $id): array
    {
        $user = Auth::user();

        $trip = $this->tripRepository->findActiveWithRelations($id);

        if (!$trip) {
            return ResponseHelper::notFound('Viaje no encontrado o ha sido eliminado');
        }

        // ✅ VALIDACIÓN DE NEGOCIO ESPECÍFICA (NO DE AUTORIZACIÓN)
        if (!$trip->canUserAccess($user)) {
            return ResponseHelper::forbidden('No puedes acceder a este viaje específico');
        }

        $tripDTO = TripMapper::modelToDTO($trip);
        return ResponseHelper::success($tripDTO, 'Viaje obtenido exitosamente');
    }

    /**
     *  CREAR TRIP CON NUEVA LÓGICA DE ASIGNACIÓN DE USUARIO
     */
    public function createTrip(array $data): array
    {
        $user = Auth::user();
        
        // ✅ LÓGICA DE NEGOCIO: Asignación de usuario
        if ($user->hasRole('Vendedor')) {
            $data['user_id'] = $user->id;
        } elseif ($user->hasAnyRole(['Administrador', 'Super Admin'])) {
            if (empty($data['user_id'])) {
                $data['user_id'] = $user->id;
            }
        }

        try {
            $trip = $this->tripRepository->create($data);
            $tripDTO = TripMapper::modelToDTO($trip);

            $travelExpenseEgress = $trip->getTravelExpenseEgress();
            
            $messageCreator = $trip->user_id === $user->id ? 'para ti' : "para el usuario ID {$trip->user_id}";
            
            return ResponseHelper::created($tripDTO, "Viaje creado exitosamente {$messageCreator}" . 
                ($travelExpenseEgress ? ' (incluyendo egreso por gastos de viaje)' : ''));

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     *  ACTUALIZAR TRIP CON SINCRONIZACIÓN DE EGRESO
     */
    public function updateTrip(int $id, array $data): array
    {
        $user = Auth::user();

        try {
            $trip = $this->tripRepository->update($id, $data, $user);

            if (!$trip) {
                return ResponseHelper::notFound('Viaje no encontrado o no tienes permisos para editarlo');
            }

            $tripDTO = TripMapper::modelToDTO($trip);

            return ResponseHelper::success($tripDTO, 'Viaje actualizado exitosamente');

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     *  ELIMINAR TRIP CON EGRESO ASOCIADO
     */
    public function deleteTrip(int $id): array
    {
        $user = Auth::user();

        try {
            $tripBeforeDelete = $this->tripRepository->findActiveWithRelations($id);
            $travelExpenseEgress = $tripBeforeDelete?->getTravelExpenseEgress();

            $trip = $this->tripRepository->softDelete($id, $user);

            if (!$trip) {
                return ResponseHelper::notFound('Viaje no encontrado o no tienes permisos para eliminarlo');
            }

            $deletedTripDTO = TripMapper::modelToDeletedDTO($trip);

            return ResponseHelper::success($deletedTripDTO, 'Viaje eliminado exitosamente' . 
                ($travelExpenseEgress ? ' (incluyendo egreso asociado)' : ''));

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     *  ELIMINAR TRIP PERMANENTEMENTE CON EGRESO - MEJORADO
     */
    public function forceDeleteTrip(int $id): array
    {
        $user = Auth::user();

        try {
            $tripBeforeDelete = $this->tripRepository->findWithTrashedAndRelations($id);
            
            if (!$tripBeforeDelete) {
                return ResponseHelper::notFound('Viaje no encontrado');
            }

            $dependencies = $tripBeforeDelete->getDependenciesDetails();
            
            $travelExpenseEgress = null;
            if ($tripBeforeDelete) {
                $travelExpenseEgress = Egress::withTrashed()
                    ->where('trip_id', $tripBeforeDelete->id)
                    ->where('name', 'LIKE', 'Gastos de viaje - %')
                    ->first();
            }

            if (!$tripBeforeDelete->canUserEdit($user)) {
                return ResponseHelper::forbidden('No tienes permisos para eliminar este viaje');
            }

            $trip = $this->tripRepository->forceDelete($id, $user);

            if (!$trip) {
                return ResponseHelper::notFound('Viaje no encontrado o no tienes permisos para eliminarlo');
            }

            $tripName = $trip->name;
            $deletedTripDTO = TripMapper::modelToDeletedDTO($trip);

            return ResponseHelper::success($deletedTripDTO, 
                "Viaje '{$tripName}' eliminado permanentemente" . 
                ($travelExpenseEgress ? ' (incluyendo egreso asociado)' : ''));

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'dependencias')) {
                return ResponseHelper::error($e->getMessage(), 422);
            }
            
            throw $e;
        }
    }

    /**
     *  LISTA PARA DROPDOWNS (con permisos)
     */
    public function getTripsList(): array
    {
        $user = Auth::user();

        $trips = $this->tripRepository->getActiveForDropdown($user);
        $dropdownData = TripMapper::collectionToDropdownDTOs($trips);

        return ResponseHelper::success($dropdownData, 'Lista de viajes obtenida exitosamente');
    }
}