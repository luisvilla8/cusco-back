<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agent\{StoreAgentRequest, UpdateAgentRequest, IndexAgentRequest};
use App\Services\AgentService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class AgentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private AgentService $agentService
    ) {}

    /**
     * Get all agents with pagination
     */
    public function index(IndexAgentRequest $request): JsonResponse
    {
        $result = $this->agentService->getAllAgents($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Get single agent
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->agentService->getAgent($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Create new agent
     */
    public function store(StoreAgentRequest $request): JsonResponse
    {
        $result = $this->agentService->createAgent($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Update existing agent
     */
    public function update(UpdateAgentRequest $request, int $id): JsonResponse
    {
        $result = $this->agentService->updateAgent($id, $request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Soft delete agent
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->agentService->deleteAgent($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Force delete agent
     */
    public function forceDelete(int $id): JsonResponse
    {
        $result = $this->agentService->forceDeleteAgent($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Get agents list for dropdown
     */
    public function list(): JsonResponse
    {
        $result = $this->agentService->getAgentsList();
        return $this->handleServiceResult($result);
    }

    /**
     * Get clients
     */
    public function clients(): JsonResponse
    {
        $result = $this->agentService->getClients();
        return $this->handleServiceResult($result);
    }

    /**
     * Get providers
     */
    public function providers(): JsonResponse
    {
        $result = $this->agentService->getProviders();
        return $this->handleServiceResult($result);
    }

    /**
     * Get agents by type
     */
    public function byType(int $typeId): JsonResponse
    {
        $result = $this->agentService->getAgentsByType($typeId);
        return $this->handleServiceResult($result);
    }

    /**
     * Fetch RUC data from external API
     */
    public function fetchRUC(string $ruc): JsonResponse
    {
        $result = $this->agentService->fetchRUC($ruc);
        return $this->handleServiceResult($result);
    }

    /**
     * Fetch DNI data from external API
     */
    public function fetchDNI(string $dni): JsonResponse
    {
        $result = $this->agentService->fetchDNI($dni);
        return $this->handleServiceResult($result);
    }
}