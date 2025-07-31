<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\Auth\AuthResource;
use App\Http\Resources\Api\V1\User\UserResource;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends BaseController
{
    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * Handle user login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        if (!$result['success']) {
            return $this->sendError($result['message'], [], (int) $result['code']);
        }

        return $this->sendResponse(
            new AuthResource($result['data']),
            $result['message']
        );
    }

    /**
     * Handle user registration
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        if (!$result['success']) {
            return $this->sendError($result['message'], [], (int) $result['code']);
        }

        return $this->sendCreated(
            new AuthResource($result['data']),
            $result['message']
        );
    }

    /**
     * Handle user logout
     */
    public function logout(Request $request): JsonResponse
    {
        $result = $this->authService->logout($request->user());

        if (!$result['success']) {
            return $this->sendError($result['message'], [], (int) $result['code']);
        }

        return $this->sendResponse([], $result['message']);
    }

    /**
     * Get authenticated user profile
     */
    public function profile(Request $request): JsonResponse
    {
        $result = $this->authService->getProfile($request->user());

        if (!$result['success']) {
            return $this->sendError($result['message'], [], (int) $result['code']);
        }

        return $this->sendResponse(
            new UserResource($result['data']->user),
            $result['message']
        );
    }

    /**
     * Refresh authentication token
     */
    public function refresh(Request $request): JsonResponse
    {
        $result = $this->authService->refreshToken($request->user());

        if (!$result['success']) {
            return $this->sendError($result['message'], [], (int) $result['code']);
        }

        return $this->sendResponse(
            new AuthResource($result['data']),
            $result['message']
        );
    }

    /**
     * Revoke all user tokens
     */
    public function revokeAllTokens(Request $request): JsonResponse
    {
        $result = $this->authService->revokeAllTokens($request->user());

        if (!$result['success']) {
            return $this->sendError($result['message'], [], (int) $result['code']);
        }

        return $this->sendResponse([], $result['message']);
    }
}
