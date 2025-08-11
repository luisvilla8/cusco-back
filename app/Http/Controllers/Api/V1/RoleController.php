<?php

namespace App\Http\Controllers\Api\V1;

use App\Attributes\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Role\{StoreRoleRequest, UpdateRoleRequest, IndexRoleRequest};
use App\Services\RoleService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;


#[Role(['Administrador'])]
class RoleController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private RoleService $roleService
    ) {}


    public function index(IndexRoleRequest $request): JsonResponse
    {
        $result = $this->roleService->getAllRoles($request->validated());
        return $this->handleServiceResult($result);
    }

    #[Role(['Administrador', 'Vendedor'])]
    public function show(int $id): JsonResponse
    {
        $result = $this->roleService->getRole($id);
        return $this->handleServiceResult($result);
    }
    
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $result = $this->roleService->createRole($request->validated());
        return $this->handleServiceResult($result);
    }

    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $result = $this->roleService->updateRole($id, $request->validated());
        return $this->handleServiceResult($result);
    }

    public function destroy(int $id): JsonResponse
    {
        $result = $this->roleService->deleteRole($id);
        return $this->handleServiceResult($result);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $result = $this->roleService->forceDeleteRole($id);
        return $this->handleServiceResult($result);
    }

    #[Role(['Administrador', 'Vendedor'])]
    public function list(): JsonResponse
    {
        $result = $this->roleService->getRolesList();
        return $this->handleServiceResult($result);
    }
}
