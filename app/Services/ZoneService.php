<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\ZoneService.php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\ZoneMapper;
use App\Repositories\ZoneRepository;
use App\Traits\LoggingTrait;

class ZoneService
{
    use LoggingTrait;

    public function __construct(
        private ZoneRepository $zoneRepository
    ) {}

    /**
     * Get all zones with pagination
     */
    public function getAllZones(array $filters = []): array
    {
        $this->logInfo('Fetching zones with filters', ['filters' => $filters]);

        $paginatedZones = $this->zoneRepository->getAllActiveWithPagination($filters);
        $mapped = ZoneMapper::paginatedToDTOs($paginatedZones);

        return ResponseHelper::paginated(
            $mapped['data'],
            $paginatedZones,
            'Zonas obtenidas exitosamente'
        );
    }

    /**
     * Get single zone by ID
     */
    public function getZone(int $id): array
    {
        $this->logInfo('Fetching zone', ['zone_id' => $id]);

        $zone = $this->zoneRepository->findActiveWithCount($id);

        if (!$zone) {
            return ResponseHelper::notFound('Zona no encontrada o ha sido eliminada');
        }

        $zoneDTO = ZoneMapper::modelToDTO($zone);
        return ResponseHelper::success($zoneDTO, 'Zona obtenida exitosamente');
    }

    /**
     * Create new zone
     */
    public function createZone(array $data): array
    {
        $this->logInfo('Creating new zone', ['data' => $data]);

        $zone = $this->zoneRepository->create($data);
        $zoneDTO = ZoneMapper::modelToDTO($zone);

        $this->logInfo('Zone created successfully', ['zone_id' => $zone->id]);

        return ResponseHelper::created($zoneDTO, 'Zona creada exitosamente');
    }

    /**
     * Update existing zone
     */
    public function updateZone(int $id, array $data): array
    {
        $this->logInfo('Updating zone', ['zone_id' => $id, 'data' => $data]);

        $zone = $this->zoneRepository->update($id, $data);

        if (!$zone) {
            return ResponseHelper::notFound('Zona no encontrada o ha sido eliminada');
        }

        $zoneDTO = ZoneMapper::modelToDTO($zone);

        $this->logInfo('Zone updated successfully', ['zone_id' => $id]);

        return ResponseHelper::success($zoneDTO, 'Zona actualizada exitosamente');
    }

    /**
     * Soft delete zone
     */
    public function deleteZone(int $id): array
    {
        $this->logInfo('Attempting to delete zone', ['zone_id' => $id]);

        $zone = $this->zoneRepository->findActiveWithCount($id);

        if (!$zone) {
            return ResponseHelper::notFound('Zona no encontrada o ha sido eliminada');
        }

        $deletedZone = $this->zoneRepository->softDelete($id);
        $deletedZoneDTO = ZoneMapper::modelToDeletedDTO($deletedZone);

        $this->logInfo('Zone deleted successfully', ['zone_id' => $id]);

        return ResponseHelper::success($deletedZoneDTO, 'Zona eliminada exitosamente');
    }

    /**
     * Force delete zone
     */
    public function forceDeleteZone(int $id): array
    {
        $this->logInfo('Attempting to force delete zone', ['zone_id' => $id]);

        $zone = $this->zoneRepository->findWithTrashedAndCount($id);

        if (!$zone) {
            return ResponseHelper::notFound('Zona no encontrada');
        }

        $zoneName = $zone->name;

        $deletedZone = $this->zoneRepository->forceDelete($id);
        $deletedZoneDTO = ZoneMapper::modelToDeletedDTO($deletedZone);

        $this->logWarning('Zone force deleted', [
            'zone_id' => $id,
            'zone_name' => $zoneName,
            'action' => 'PERMANENT_DELETE'
        ]);

        return ResponseHelper::success($deletedZoneDTO, "Zona '{$zoneName}' eliminada permanentemente");
    }

    /**
     * Get zones list for dropdown
     */
    public function getZonesList(): array
    {
        $this->logInfo('Fetching zones list for dropdown');

        $zones = $this->zoneRepository->getActiveForDropdown();
        $dropdownData = ZoneMapper::collectionToDropdownDTOs($zones);

        return ResponseHelper::success($dropdownData, 'Lista de zonas obtenida exitosamente');
    }

    
}