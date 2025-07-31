<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

abstract class BaseService
{
    /**
     * Handle exceptions and return standardized response
     */
    protected function handleException(\Exception $exception): array
    {
        return [
            'success' => false,
            'message' => 'Error: ' . $exception->getMessage(),
            'code' => $exception->getCode() ?: 500
        ];
    }

    /**
     * Success response format
     */
    protected function successResponse($data, string $message = '', int $code = 200): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'code' => $code
        ];
    }

    /**
     * Error response format
     */
    protected function errorResponse(string $message, int $code = 400): array
    {
        return [
            'success' => false,
            'message' => $message,
            'code' => $code
        ];
    }

    /**
     * Created response format (for POST operations)
     */
    protected function createdResponse($data, string $message = ''): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'code' => 201
        ];
    }

    /**
     * Not found response format
     */
    protected function notFoundResponse(string $message = 'Resource not found'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'code' => 404
        ];
    }

    /**
     * Validation error response format
     */
    protected function validationErrorResponse(string $message = 'Validation failed'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'code' => 422
        ];
    }
}

class AuthService extends BaseService
{
    /**
     * Handle user login
     */
    public function login(array $credentials): array
    {
        try {
            // Solo buscar usuarios activos (no eliminados)
            $user = User::active()
                ->where('email', $credentials['email'])
                ->with('role')
                ->first();

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return $this->errorResponse('Credenciales incorrectas', 401);
            }

            // Crear token
            $tokenName = 'auth_token_' . now()->timestamp;
            $token = $user->createToken($tokenName);

            $data = (object) [
                'user' => $user,
                'token' => $token->plainTextToken,
                'expires_in' => 24 * 60 * 60 // 24 horas
            ];

            return $this->successResponse($data, 'Login exitoso');

        } catch (\Exception $e) {
            Log::error('Error en login: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Handle user registration
     */
    public function register(array $data): array
    {
        try {
            DB::beginTransaction();

            // Asignar rol por defecto si no se especifica
            if (!isset($data['role_id']) || empty($data['role_id'])) {
                $defaultRole = Role::where('name', 'User')->first();
                if (!$defaultRole) {
                    // Crear rol por defecto si no existe
                    $defaultRole = Role::create([
                        'name' => 'User',
                        'description' => 'Usuario regular'
                    ]);
                }
                $data['role_id'] = $defaultRole->id;
            }

            // Crear usuario
            $user = User::create($data);

            // Crear token automáticamente
            $token = $user->createToken('auth_token_' . now()->timestamp);

            // Cargar relaciones
            $user->load('role');

            DB::commit();

            $responseData = (object) [
                'user' => $user,
                'token' => $token->plainTextToken,
                'expires_in' => 24 * 60 * 60 // 24 horas
            ];

            return $this->createdResponse($responseData, 'Usuario registrado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en register: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Handle user logout
     */
    public function logout(User $user): array
    {
        try {
            // Eliminar el token específico del usuario autenticado
            $currentToken = $user->currentAccessToken();
            if ($currentToken) {
                $user->tokens()->where('id', $currentToken->id)->delete();
            }

            return $this->successResponse([], 'Logout exitoso');

        } catch (\Exception $e) {
            Log::error('Error en logout: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Get authenticated user profile
     */
    public function getProfile(User $user): array
    {
        try {
            $user->load('role');

            $data = (object) [
                'user' => $user,
            ];

            return $this->successResponse($data, 'Perfil obtenido exitosamente');

        } catch (\Exception $e) {
            Log::error('Error en getProfile: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Refresh authentication token
     */
    public function refreshToken(User $user): array
    {
        try {
            // Eliminar token actual
            $currentToken = $user->currentAccessToken();
            if ($currentToken) {
                $user->tokens()->where('id', $currentToken->id)->delete();
            }

            // Crear nuevo token
            $token = $user->createToken('auth_token_' . now()->timestamp);

            $data = (object) [
                'user' => $user->load('role'),
                'token' => $token->plainTextToken,
                'expires_in' => 24 * 60 * 60 // 24 horas
            ];

            return $this->successResponse($data, 'Token renovado exitosamente');

        } catch (\Exception $e) {
            Log::error('Error en refreshToken: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Revoke all user tokens
     */
    public function revokeAllTokens(User $user): array
    {
        try {
            // Eliminar todos los tokens del usuario
            $user->tokens()->delete();

            return $this->successResponse([], 'Todos los tokens han sido revocados');

        } catch (\Exception $e) {
            Log::error('Error en revokeAllTokens: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }
}
