<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\{LoginRequest, RegisterRequest, ChangePasswordRequest};
use App\Services\AuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * Handle user login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Handle user registration
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Handle user logout
     */
    public function logout(Request $request): JsonResponse
    {
        $result = $this->authService->logout($request->user());
        return $this->handleServiceResult($result);
    }

    /**
     * Get authenticated user profile
     */
    public function profile(Request $request): JsonResponse
    {
        $result = $this->authService->getProfile($request->user());
        return $this->handleServiceResult($result);
    }

    /**
     * Refresh authentication token
     */
    public function refresh(Request $request): JsonResponse
    {
        $result = $this->authService->refreshToken($request->user());
        return $this->handleServiceResult($result);
    }

    /**
     * Revoke all user tokens
     */
    public function revokeAllTokens(Request $request): JsonResponse
    {
        $result = $this->authService->revokeAllTokens($request->user());
        return $this->handleServiceResult($result);
    }

    /**
     * Change user password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $result = $this->authService->changePassword(
            $request->user(),
            $request->validated()['current_password'],
            $request->validated()['new_password']
        );
        return $this->handleServiceResult($result);
    }
}
