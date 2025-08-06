<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Controllers\Api\V1\UserController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\{IndexUserRequest, StoreUserRequest, UpdateUserRequest};
use App\Services\UserService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Obtener todos los usuarios con paginación y filtros
     */
    public function index(IndexUserRequest $request): JsonResponse
    {
        $result = $this->userService->getAllUsers($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Obtener un usuario específico
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->userService->getUser($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Obtener lista de usuarios para dropdowns
     */
    public function list(): JsonResponse
    {
        $result = $this->userService->getUsersList();
        return $this->handleServiceResult($result);
    }

    /**
     * Crear nuevo usuario
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $result = $this->userService->createUser($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Actualizar usuario existente (JSON)
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $result = $this->userService->updateUser($id, $request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Eliminar usuario (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->userService->deleteUser($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Eliminar usuario permanentemente
     */
    public function forceDelete(int $id): JsonResponse
    {
        $result = $this->userService->forceDeleteUser($id);
        return $this->handleServiceResult($result);
    }

}