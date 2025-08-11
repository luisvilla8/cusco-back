<?php

namespace App\Http\Controllers\Api\V1;

use App\Attributes\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AgentType\{StoreAgentTypeRequest, UpdateAgentTypeRequest, IndexAgentTypeRequest};
use App\Services\AgentTypeService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;


#[Role(['Administrador'])]
class AgentTypeController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private AgentTypeService $agentTypeService
    ) {}

    /**
     * Get all agent types with pagination
     */
    #[Role(['Administrador', 'Vendedor'], 'Solo administradores y vendedores pueden ver la lista de usuarios')]
    public function index(IndexAgentTypeRequest $request): JsonResponse
    {
        $result = $this->agentTypeService->getAllAgentTypes($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Get single agent type
     */
    #[Role(['Administrador', 'Vendedor'], 'Solo administradores y vendedores pueden ver la lista de usuarios')]
    public function show(int $id): JsonResponse
    {
        $result = $this->agentTypeService->getAgentType($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Create new agent type
     */
    public function store(StoreAgentTypeRequest $request): JsonResponse
    {
        $result = $this->agentTypeService->createAgentType($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Update existing agent type
     */
    public function update(UpdateAgentTypeRequest $request, int $id): JsonResponse
    {
        $result = $this->agentTypeService->updateAgentType($id, $request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Soft delete agent type
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->agentTypeService->deleteAgentType($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Force delete agent type
     */
    public function forceDelete(int $id): JsonResponse
    {
        $result = $this->agentTypeService->forceDeleteAgentType($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Get agent types list for dropdown
     */
    #[Role(['Administrador', 'Vendedor'], 'Solo administradores y vendedores pueden ver la lista de usuarios')]
    public function list(): JsonResponse
    {
        $result = $this->agentTypeService->getAgentTypesList();
        return $this->handleServiceResult($result);
    }
}
