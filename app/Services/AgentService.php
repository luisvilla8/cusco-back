<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\AgentService.php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\AgentMapper;
use App\Repositories\AgentRepository;
use App\Traits\LoggingTrait;

class AgentService
{
    use LoggingTrait;

    public function __construct(
        private AgentRepository $agentRepository
    ) {}

    /**
     * Get all agents with pagination
     */
    public function getAllAgents(array $filters = []): array
    {
        $this->logInfo('Fetching agents with filters', ['filters' => $filters]);

        $paginatedAgents = $this->agentRepository->getAllActiveWithPagination($filters);
        $mapped = AgentMapper::paginatedToDTOs($paginatedAgents);

        return ResponseHelper::paginated(
            $mapped['data'],
            $paginatedAgents,
            'Agentes obtenidos exitosamente'
        );
    }

    /**
     * Get single agent by ID
     */
    public function getAgent(int $id): array
    {
        $this->logInfo('Fetching agent', ['agent_id' => $id]);

        $agent = $this->agentRepository->findActiveWithRelations($id);

        if (!$agent) {
            return ResponseHelper::notFound('Agente no encontrado o ha sido eliminado');
        }

        $agentDTO = AgentMapper::modelToDTO($agent);
        return ResponseHelper::success($agentDTO, 'Agente obtenido exitosamente');
    }

    /**
     * Create new agent
     */
    public function createAgent(array $data): array
    {
        $this->logInfo('Creating new agent', ['data' => $data]);

        $agent = $this->agentRepository->create($data);
        $agentDTO = AgentMapper::modelToDTO($agent);

        $this->logInfo('Agent created successfully', ['agent_id' => $agent->id]);

        return ResponseHelper::created($agentDTO, 'Agente creado exitosamente');
    }

    /**
     * Update existing agent
     */
    public function updateAgent(int $id, array $data): array
    {
        $this->logInfo('Updating agent', ['agent_id' => $id, 'data' => $data]);

        $agent = $this->agentRepository->update($id, $data);

        if (!$agent) {
            return ResponseHelper::notFound('Agente no encontrado o ha sido eliminado');
        }

        $agentDTO = AgentMapper::modelToDTO($agent);

        $this->logInfo('Agent updated successfully', ['agent_id' => $id]);

        return ResponseHelper::success($agentDTO, 'Agente actualizado exitosamente');
    }

    /**
     * Soft delete agent
     */
    public function deleteAgent(int $id): array
    {
        $this->logInfo('Attempting to delete agent', ['agent_id' => $id]);

        $agent = $this->agentRepository->findActiveWithRelations($id);

        if (!$agent) {
            return ResponseHelper::notFound('Agente no encontrado o ha sido eliminado');
        }

        $deletedAgent = $this->agentRepository->softDelete($id);
        $deletedAgentDTO = AgentMapper::modelToDeletedDTO($deletedAgent);

        $this->logInfo('Agent deleted successfully', ['agent_id' => $id]);

        return ResponseHelper::success($deletedAgentDTO, 'Agente eliminado exitosamente');
    }

    /**
     * Force delete agent
     */
    public function forceDeleteAgent(int $id): array
    {
        $this->logInfo('Attempting to force delete agent', ['agent_id' => $id]);

        $agent = $this->agentRepository->findWithTrashedAndRelations($id);

        if (!$agent) {
            return ResponseHelper::notFound('Agente no encontrado');
        }

        $agentName = $agent->name;

        $deletedAgent = $this->agentRepository->forceDelete($id);
        $deletedAgentDTO = AgentMapper::modelToDeletedDTO($deletedAgent);

        $this->logWarning('Agent force deleted', [
            'agent_id' => $id,
            'agent_name' => $agentName,
            'action' => 'PERMANENT_DELETE'
        ]);

        return ResponseHelper::success($deletedAgentDTO, "Agente '{$agentName}' eliminado permanentemente");
    }

    /**
     * Get agents list for dropdown
     */
    public function getAgentsList(): array
    {
        $this->logInfo('Fetching agents list for dropdown');

        $agents = $this->agentRepository->getActiveForDropdown();
        $dropdownData = AgentMapper::collectionToDropdownDTOs($agents);

        return ResponseHelper::success($dropdownData, 'Lista de agentes obtenida exitosamente');
    }

    /**
     * Get clients
     */
    public function getClients(): array
    {
        $this->logInfo('Fetching clients');

        $clients = $this->agentRepository->getClients();
        $clientsData = AgentMapper::collectionToArrays($clients);

        return ResponseHelper::success($clientsData, 'Clientes obtenidos exitosamente');
    }

    /**
     * Get providers
     */
    public function getProviders(): array
    {
        $this->logInfo('Fetching providers');

        $providers = $this->agentRepository->getProviders();
        $providersData = AgentMapper::collectionToArrays($providers);

        return ResponseHelper::success($providersData, 'Proveedores obtenidos exitosamente');
    }

    /**
     * Get agents by type
     */
    public function getAgentsByType(int $typeId): array
    {
        $this->logInfo('Fetching agents by type', ['type_id' => $typeId]);

        $agents = $this->agentRepository->getByType($typeId);
        $agentsData = AgentMapper::collectionToArrays($agents);

        return ResponseHelper::success($agentsData, 'Agentes obtenidos exitosamente');
    }

    /**
     * Fetch agent data by RUC from external API
     */
    public function fetchRUC(string $ruc): array
    {
        $this->logInfo('Fetching RUC data', ['ruc' => $ruc]);

        // Primero verificar si ya existe
        $existingAgent = $this->agentRepository->findByRuc($ruc);
        if ($existingAgent) {
            return ResponseHelper::error('Ya existe un agente con este RUC', 409);
        }

        // TODO: Integrar con API externa (SUNAT)
        // Por ahora retornar datos de ejemplo
        $mockData = [
            'ruc' => $ruc,
            'name' => 'Empresa Ejemplo S.A.C.',
            'address' => 'Av. Ejemplo 123, Lima',
            'status' => 'ACTIVO',
        ];

        return ResponseHelper::success($mockData, 'Datos de RUC obtenidos exitosamente');
    }

    /**
     * Fetch agent data by DNI from external API
     */
    public function fetchDNI(string $dni): array
    {
        $this->logInfo('Fetching DNI data', ['dni' => $dni]);

        // Primero verificar si ya existe
        $existingAgent = $this->agentRepository->findByDni($dni);
        if ($existingAgent) {
            return ResponseHelper::error('Ya existe un agente con este DNI', 409);
        }

        // TODO: Integrar con API externa (RENIEC)
        // Por ahora retornar datos de ejemplo
        $mockData = [
            'dni' => $dni,
            'name' => 'Juan Pérez García',
            'status' => 'ACTIVO',
        ];

        return ResponseHelper::success($mockData, 'Datos de DNI obtenidos exitosamente');
    }
}