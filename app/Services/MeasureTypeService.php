<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\MeasureTypeService.php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\MeasureTypeMapper;
use App\Repositories\MeasureTypeRepository;
use App\Traits\LoggingTrait;

class MeasureTypeService
{
    use LoggingTrait;

    public function __construct(
        private MeasureTypeRepository $measureTypeRepository
    ) {}

    /**
     * Get all measure types with pagination
     */
    public function getAllMeasureTypes(array $filters = []): array
    {
        $this->logInfo('Fetching measure types with filters', ['filters' => $filters]);

        $paginatedMeasureTypes = $this->measureTypeRepository->getAllActiveWithPagination($filters);
        $mapped = MeasureTypeMapper::paginatedToDTOs($paginatedMeasureTypes);

        return ResponseHelper::paginated(
            $mapped['data'],
            $paginatedMeasureTypes,
            'Unidades de medida obtenidas exitosamente'
        );
    }

    /**
     * Get single measure type by ID
     */
    public function getMeasureType(int $id): array
    {
        $this->logInfo('Fetching measure type', ['measure_type_id' => $id]);

        $measureType = $this->measureTypeRepository->findActiveWithCount($id);

        if (!$measureType) {
            return ResponseHelper::notFound('Unidad de medida no encontrada o ha sido eliminada');
        }

        $measureTypeDTO = MeasureTypeMapper::modelToDTO($measureType);
        return ResponseHelper::success($measureTypeDTO, 'Unidad de medida obtenida exitosamente');
    }

    /**
     * Create new measure type
     */
    public function createMeasureType(array $data): array
    {
        $this->logInfo('Creating new measure type', ['data' => $data]);

        $measureType = $this->measureTypeRepository->create($data);
        $measureTypeDTO = MeasureTypeMapper::modelToDTO($measureType);

        $this->logInfo('Measure type created successfully', ['measure_type_id' => $measureType->id]);

        return ResponseHelper::created($measureTypeDTO, 'Unidad de medida creada exitosamente');
    }

    /**
     * Update existing measure type
     */
    public function updateMeasureType(int $id, array $data): array
    {
        $this->logInfo('Updating measure type', ['measure_type_id' => $id, 'data' => $data]);

        $measureType = $this->measureTypeRepository->update($id, $data);

        if (!$measureType) {
            return ResponseHelper::notFound('Unidad de medida no encontrada o ha sido eliminada');
        }

        $measureTypeDTO = MeasureTypeMapper::modelToDTO($measureType);

        $this->logInfo('Measure type updated successfully', ['measure_type_id' => $id]);

        return ResponseHelper::success($measureTypeDTO, 'Unidad de medida actualizada exitosamente');
    }

    /**
     * Soft delete measure type
     */
    public function deleteMeasureType(int $id): array
    {
        $this->logInfo('Attempting to delete measure type', ['measure_type_id' => $id]);

        $measureType = $this->measureTypeRepository->findActiveWithCount($id);

        if (!$measureType) {
            return ResponseHelper::notFound('Unidad de medida no encontrada o ha sido eliminada');
        }

        $deletedMeasureType = $this->measureTypeRepository->softDelete($id);
        $deletedMeasureTypeDTO = MeasureTypeMapper::modelToDeletedDTO($deletedMeasureType);

        $this->logInfo('Measure type deleted successfully', ['measure_type_id' => $id]);

        return ResponseHelper::success($deletedMeasureTypeDTO, 'Unidad de medida eliminada exitosamente');
    }

    /**
     * Force delete measure type
     */
    public function forceDeleteMeasureType(int $id): array
    {
        $this->logInfo('Attempting to force delete measure type', ['measure_type_id' => $id]);

        $measureType = $this->measureTypeRepository->findWithTrashedAndCount($id);

        if (!$measureType) {
            return ResponseHelper::notFound('Unidad de medida no encontrada');
        }

        $measureTypeName = $measureType->name; // ✅ CAMBIO: description → name

        $deletedMeasureType = $this->measureTypeRepository->forceDelete($id);
        $deletedMeasureTypeDTO = MeasureTypeMapper::modelToDeletedDTO($deletedMeasureType);

        $this->logWarning('Measure type force deleted', [
            'measure_type_id' => $id,
            'measure_type_name' => $measureTypeName,
            'action' => 'PERMANENT_DELETE'
        ]);

        return ResponseHelper::success($deletedMeasureTypeDTO, "Unidad de medida '{$measureTypeName}' eliminada permanentemente");
    }

    /**
     * Get measure types list for dropdown
     */
    public function getMeasureTypesList(): array
    {
        $this->logInfo('Fetching measure types list for dropdown');

        $measureTypes = $this->measureTypeRepository->getActiveForDropdown();
        $dropdownData = MeasureTypeMapper::collectionToDropdownDTOs($measureTypes);

        return ResponseHelper::success($dropdownData, 'Lista de unidades de medida obtenida exitosamente');
    }
}