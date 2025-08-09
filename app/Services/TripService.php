<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\TripService.php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\TripMapper;
use App\Models\Egress;
use App\Repositories\TripRepository;
use App\Traits\LoggingTrait;
use Illuminate\Support\Facades\Auth;

class TripService
{
    use LoggingTrait;

    public function __construct(
        private TripRepository $tripRepository
    ) {}

    /**
     *  OBTENER TODOS LOS TRIPS (con permisos)
     */
    public function getAllTrips(array $filters = []): array
    {
        $user = Auth::user();
        $this->logInfo('Fetching trips with filters', [
            'filters' => $filters,
            'user_id' => $user->id,
            'user_role' => $user->getRoleName()
        ]);

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
        $this->logInfo('Fetching trip', ['trip_id' => $id, 'user_id' => $user->id]);

        if (!$this->tripRepository->canUserAccessTrip($id, $user)) {
            return ResponseHelper::forbidden('No tienes acceso a este viaje');
        }

        $trip = $this->tripRepository->findActiveWithRelations($id);

        if (!$trip) {
            return ResponseHelper::notFound('Viaje no encontrado o ha sido eliminado');
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
        
        //  NUEVA LÓGICA DE ASIGNACIÓN DE USUARIO
        if ($user->hasRole('Vendedor')) {
            // VENDEDORES: SIEMPRE usar su propio ID (ignorar user_id del request)
            $data['user_id'] = $user->id;
            
            $this->logInfo('Vendedor creating trip - using Bearer token user_id', [
                'vendedor_id' => $user->id,
                'vendedor_name' => $user->name,
                'requested_user_id' => $data['user_id'] ?? 'none',
                'final_user_id' => $data['user_id'],
                'trip_data' => $data
            ]);
            
        } elseif ($user->hasAnyRole(['Administrador', 'Super Admin'])) {
            // ADMINISTRADORES: Usar user_id del request si existe, sino usar su propio ID
            if (!empty($data['user_id'])) {
                // Usar el user_id proporcionado en el request
                $this->logInfo('Admin creating trip - using request user_id', [
                    'admin_id' => $user->id,
                    'admin_name' => $user->name,
                    'requested_user_id' => $data['user_id'],
                    'final_user_id' => $data['user_id'],
                    'trip_data' => $data
                ]);
            } else {
                // No se proporcionó user_id, usar el del Bearer token
                $data['user_id'] = $user->id;
                
                $this->logInfo('Admin creating trip - using Bearer token user_id (no request user_id)', [
                    'admin_id' => $user->id,
                    'admin_name' => $user->name,
                    'requested_user_id' => 'none',
                    'final_user_id' => $data['user_id'],
                    'trip_data' => $data
                ]);
            }
        } else {
            // Rol no autorizado (aunque ya se validó en el Request)
            return ResponseHelper::forbidden('Tu rol no tiene permisos para crear viajes');
        }

        $this->logInfo('Creating new trip with automatic egress', [
            'data' => $data,
            'creator_id' => $user->id,
            'creator_role' => $user->getRoleName(),
            'assigned_user_id' => $data['user_id'],
            'travel_expenses' => $data['travel_expenses'] ?? 0,
            'assignment_logic' => $user->hasRole('Vendedor') ? 'vendedor_self_only' : 'admin_flexible'
        ]);

        try {
            $trip = $this->tripRepository->create($data);
            $tripDTO = TripMapper::modelToDTO($trip);

            //  LOG SI SE CREÓ EGRESO AUTOMÁTICAMENTE
            $travelExpenseEgress = $trip->getTravelExpenseEgress();
            if ($travelExpenseEgress) {
                $this->logInfo('Travel expense egress created automatically', [
                    'trip_id' => $trip->id,
                    'egress_id' => $travelExpenseEgress->id,
                    'amount' => $travelExpenseEgress->amount
                ]);
            }

            $this->logInfo('Trip created successfully with new user assignment logic', [
                'trip_id' => $trip->id,
                'creator_id' => $user->id,
                'creator_role' => $user->getRoleName(),
                'assigned_user_id' => $trip->user_id,
                'creator_is_assigned_user' => $trip->user_id === $user->id,
                'has_travel_expenses' => $trip->travel_expenses > 0,
                'egress_created' => $travelExpenseEgress ? true : false
            ]);

            $messageCreator = $trip->user_id === $user->id ? 'para ti' : "para el usuario ID {$trip->user_id}";
            
            return ResponseHelper::created($tripDTO, "Viaje creado exitosamente {$messageCreator}" . 
                ($travelExpenseEgress ? ' (incluyendo egreso por gastos de viaje)' : ''));

        } catch (\Exception $e) {
            $this->logError('Error creating trip', [
                'creator_id' => $user->id,
                'creator_role' => $user->getRoleName(),
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     *  ACTUALIZAR TRIP CON SINCRONIZACIÓN DE EGRESO
     */
    public function updateTrip(int $id, array $data): array
    {
        $user = Auth::user();
        $this->logInfo('Updating trip with egress sync', [
            'trip_id' => $id,
            'data' => $data,
            'user_id' => $user->id
        ]);

        try {
            $trip = $this->tripRepository->update($id, $data, $user);

            if (!$trip) {
                return ResponseHelper::notFound('Viaje no encontrado o no tienes permisos para editarlo');
            }

            //  LOG CAMBIOS EN EGRESO
            if (isset($data['travel_expenses'])) {
                $travelExpenseEgress = $trip->getTravelExpenseEgress();
                $this->logInfo('Travel expenses updated, egress synced', [
                    'trip_id' => $id,
                    'new_travel_expenses' => $data['travel_expenses'],
                    'egress_exists' => $travelExpenseEgress ? true : false,
                    'egress_id' => $travelExpenseEgress?->id
                ]);
            }

            $tripDTO = TripMapper::modelToDTO($trip);

            $this->logInfo('Trip updated successfully with egress sync', ['trip_id' => $id]);

            return ResponseHelper::success($tripDTO, 'Viaje actualizado exitosamente');

        } catch (\Exception $e) {
            $this->logError('Error updating trip', [
                'trip_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     *  ELIMINAR TRIP CON EGRESO ASOCIADO
     */
    public function deleteTrip(int $id): array
    {
        $user = Auth::user();
        $this->logInfo('Attempting to delete trip with associated egress', [
            'trip_id' => $id,
            'user_id' => $user->id
        ]);

        try {
            // Verificar egreso antes de eliminar
            $tripBeforeDelete = $this->tripRepository->findActiveWithRelations($id);
            $travelExpenseEgress = $tripBeforeDelete?->getTravelExpenseEgress();

            $trip = $this->tripRepository->softDelete($id, $user);

            if (!$trip) {
                return ResponseHelper::notFound('Viaje no encontrado o no tienes permisos para eliminarlo');
            }

            $deletedTripDTO = TripMapper::modelToDeletedDTO($trip);

            $this->logInfo('Trip soft deleted successfully with associated egress', [
                'trip_id' => $id,
                'egress_also_deleted' => $travelExpenseEgress ? true : false,
                'egress_id' => $travelExpenseEgress?->id
            ]);

            return ResponseHelper::success($deletedTripDTO, 'Viaje eliminado exitosamente' . 
                ($travelExpenseEgress ? ' (incluyendo egreso asociado)' : ''));

        } catch (\Exception $e) {
            $this->logError('Error deleting trip', [
                'trip_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     *  ELIMINAR TRIP PERMANENTEMENTE CON EGRESO - MEJORADO
     */
    public function forceDeleteTrip(int $id): array
    {
        $user = Auth::user();
        $this->logInfo('Attempting to force delete trip with associated egress', [
            'trip_id' => $id,
            'user_id' => $user->id
        ]);

        try {
            // Verificar egreso antes de eliminar
            $tripBeforeDelete = $this->tripRepository->findWithTrashedAndRelations($id);
            
            if (!$tripBeforeDelete) {
                return ResponseHelper::notFound('Viaje no encontrado');
            }

            //  OBTENER DETALLES DE DEPENDENCIAS ANTES DE ELIMINAR
            $dependencies = $tripBeforeDelete->getDependenciesDetails();
            
            $this->logInfo('Trip dependencies before force delete', [
                'trip_id' => $id,
                'dependencies' => $dependencies
            ]);

            $travelExpenseEgress = null;
            if ($tripBeforeDelete) {
                $travelExpenseEgress = Egress::withTrashed()
                    ->where('trip_id', $tripBeforeDelete->id)
                    ->where('name', 'LIKE', 'Gastos de viaje - %')
                    ->first();
            }

            //  VERIFICAR PERMISOS
            if (!$tripBeforeDelete->canUserEdit($user)) {
                return ResponseHelper::forbidden('No tienes permisos para eliminar este viaje');
            }

            $trip = $this->tripRepository->forceDelete($id, $user);

            if (!$trip) {
                return ResponseHelper::notFound('Viaje no encontrado o no tienes permisos para eliminarlo');
            }

            $tripName = $trip->name;
            $deletedTripDTO = TripMapper::modelToDeletedDTO($trip);

            $this->logWarning('Trip force deleted permanently with associated egress', [
                'trip_id' => $id,
                'trip_name' => $tripName,
                'user_id' => $user->id,
                'action' => 'PERMANENT_DELETE',
                'dependencies_deleted' => $dependencies,
                'egress_also_deleted' => $travelExpenseEgress ? true : false,
                'egress_id' => $travelExpenseEgress?->id
            ]);

            return ResponseHelper::success($deletedTripDTO, 
                "Viaje '{$tripName}' eliminado permanentemente" . 
                ($travelExpenseEgress ? ' (incluyendo egreso asociado)' : ''));

        } catch (\Exception $e) {
            $this->logError('Error force deleting trip', [
                'trip_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            //  MANEJAR DIFERENTES TIPOS DE ERROR
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
        $this->logInfo('Fetching trips list for dropdown', ['user_id' => $user->id]);

        $trips = $this->tripRepository->getActiveForDropdown($user);
        $dropdownData = TripMapper::collectionToDropdownDTOs($trips);

        return ResponseHelper::success($dropdownData, 'Lista de viajes obtenida exitosamente');
    }
}