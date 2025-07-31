<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\AgentTypeService.php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\AgentTypeMapper;
use App\Repositories\AgentTypeRepository;
use App\Traits\LoggingTrait;

class AgentTypeService
{
    use LoggingTrait;

    public function __construct(
        private AgentTypeRepository $agentTypeRepository
    ) {}

    /**
     * Get all agent types with pagination
     */
    public function getAllAgentTypes(array $filters = []): array
    {
        $this->logInfo('Fetching agent types with filters', ['filters' => $filters]);

        $paginatedAgentTypes = $this->agentTypeRepository->getAllActiveWithPagination($filters);
        $mapped = AgentTypeMapper::paginatedToDTOs($paginatedAgentTypes);

        return ResponseHelper::paginated(
            $mapped['data'],
            $paginatedAgentTypes,
            'Tipos de agente obtenidos exitosamente'
        );
    }

    /**
     * Get single agent type by ID
     */
    public function getAgentType(int $id): array
    {
        $this->logInfo('Fetching agent type', ['agent_type_id' => $id]);

        $agentType = $this->agentTypeRepository->findActiveWithCount($id);

        if (!$agentType) {
            return ResponseHelper::notFound('Tipo de agente no encontrado o ha sido eliminado');
        }

        $agentTypeDTO = AgentTypeMapper::modelToDTO($agentType);
        return ResponseHelper::success($agentTypeDTO, 'Tipo de agente obtenido exitosamente');
    }

    /**
     * Create new agent type
     */
    public function createAgentType(array $data): array
    {
        $this->logInfo('Creating new agent type', ['data' => $data]);

        $agentType = $this->agentTypeRepository->create($data);
        $agentTypeDTO = AgentTypeMapper::modelToDTO($agentType);

        $this->logInfo('Agent type created successfully', ['agent_type_id' => $agentType->id]);

        return ResponseHelper::created($agentTypeDTO, 'Tipo de agente creado exitosamente');
    }

    /**
     * Update existing agent type
     */
    public function updateAgentType(int $id, array $data): array
    {
        $this->logInfo('Updating agent type', ['agent_type_id' => $id, 'data' => $data]);

        $agentType = $this->agentTypeRepository->update($id, $data);

        if (!$agentType) {
            return ResponseHelper::notFound('Tipo de agente no encontrado o ha sido eliminado');
        }

        $agentTypeDTO = AgentTypeMapper::modelToDTO($agentType);

        $this->logInfo('Agent type updated successfully', ['agent_type_id' => $id]);

        return ResponseHelper::success($agentTypeDTO, 'Tipo de agente actualizado exitosamente');
    }

    /**
     * Soft delete agent type
     */
    public function deleteAgentType(int $id): array
    {
        $this->logInfo('Attempting to delete agent type', ['agent_type_id' => $id]);

        $agentType = $this->agentTypeRepository->findActiveWithCount($id);

        if (!$agentType) {
            return ResponseHelper::notFound('Tipo de agente no encontrado o ha sido eliminado');
        }

        $deletedAgentType = $this->agentTypeRepository->softDelete($id);
        $deletedAgentTypeDTO = AgentTypeMapper::modelToDeletedDTO($deletedAgentType);

        $this->logInfo('Agent type deleted successfully', ['agent_type_id' => $id]);

        return ResponseHelper::success($deletedAgentTypeDTO, 'Tipo de agente eliminado exitosamente');
    }

    /**
     * Force delete agent type
     */
    public function forceDeleteAgentType(int $id): array
    {
        $this->logInfo('Attempting to force delete agent type', ['agent_type_id' => $id]);

        $agentType = $this->agentTypeRepository->findWithTrashedAndCount($id);

        if (!$agentType) {
            return ResponseHelper::notFound('Tipo de agente no encontrado');
        }

        $agentTypeName = $agentType->name;

        $deletedAgentType = $this->agentTypeRepository->forceDelete($id);
        $deletedAgentTypeDTO = AgentTypeMapper::modelToDeletedDTO($deletedAgentType);

        $this->logWarning('Agent type force deleted', [
            'agent_type_id' => $id,
            'agent_type_name' => $agentTypeName,
            'action' => 'PERMANENT_DELETE'
        ]);

        return ResponseHelper::success($deletedAgentTypeDTO, "Tipo de agente '{$agentTypeName}' eliminado permanentemente");
    }

    /**
     * Get agent types list for dropdown
     */
    public function getAgentTypesList(): array
    {
        $this->logInfo('Fetching agent types list for dropdown');

        $agentTypes = $this->agentTypeRepository->getActiveForDropdown();
        $dropdownData = AgentTypeMapper::collectionToDropdownDTOs($agentTypes);

        return ResponseHelper::success($dropdownData, 'Lista de tipos de agente obtenida exitosamente');
    }
}