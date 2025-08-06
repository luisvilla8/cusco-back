<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\AuthService.php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\AuthMapper;
use App\Repositories\AuthRepository;
use App\Traits\LoggingTrait;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    use LoggingTrait;

    public function __construct(
        private AuthRepository $authRepository
    ) {}

    public function login(array $credentials): array
    {
        $this->logInfo('Attempting user login', ['email' => $credentials['email']]);

        $user = $this->authRepository->findUserByEmail($credentials['email']);

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            $this->logWarning('Login failed - invalid credentials', ['email' => $credentials['email']]);
            return ResponseHelper::unauthorized('Credenciales incorrectas');
        }

        $tokenName = 'auth_token_' . now()->timestamp;
        $token = $user->createToken($tokenName);
        $expiresIn = 24 * 60 * 60; // 24 horas

        $loginResponse = AuthMapper::toLoginResponse($user, $token->plainTextToken, $expiresIn);

        $this->logInfo('User logged in successfully', [
            'user_id' => $user->id,
            'user_code' => $user->code,
            'role' => $user->role?->name
        ]);

        return ResponseHelper::success(
            AuthMapper::loginResponseToArray($loginResponse),
            'Login exitoso'
        );
    }

    /**
     * ✅ AUTO-REGISTRO: Para usuarios que se registran a sí mismos
     */
    public function register(array $data): array
    {
        $this->logInfo('Attempting user auto-registration', ['email' => $data['email']]);

        try {
            $user = $this->authRepository->createUserFromRegistration($data);

            // ✅ Login automático después del registro
            $tokenName = 'auth_token_' . now()->timestamp;
            $token = $user->createToken($tokenName);
            $expiresIn = 24 * 60 * 60; // 24 horas

            $registerResponse = AuthMapper::toRegisterResponse($user, $token->plainTextToken, $expiresIn);

            $this->logInfo('User auto-registered successfully', [
                'user_id' => $user->id,
                'user_code' => $user->code,
                'role' => $user->role?->name,
                'zone' => $user->zone?->name
            ]);

            return ResponseHelper::created(
                AuthMapper::registerResponseToArray($registerResponse),
                'Usuario registrado e iniciado sesión exitosamente'
            );
        } catch (\Exception $e) {
            $this->logError('Error in auto-registration', ['email' => $data['email'], 'error' => $e->getMessage()]);
            return ResponseHelper::error('Error al registrar usuario: ' . $e->getMessage());
        }
    }

    public function logout($user): array
    {
        $this->logInfo('User logout', ['user_id' => $user->id]);
        $this->authRepository->revokeUserToken($user);
        return ResponseHelper::success([], 'Logout exitoso');
    }

    public function getProfile($user): array
    {
        $this->logInfo('Fetching user profile', ['user_id' => $user->id]);

        $userWithRelations = $this->authRepository->findUserWithTokens($user->id);
        
        if (!$userWithRelations) {
            return ResponseHelper::notFound('Usuario no encontrado');
        }

        $authUserDTO = AuthMapper::userToAuthDTO($userWithRelations);

        return ResponseHelper::success(
            AuthMapper::authUserToArray($authUserDTO),
            'Perfil obtenido exitosamente'
        );
    }

    public function refreshToken($user): array
    {
        $this->logInfo('Refreshing user token', ['user_id' => $user->id]);

        $this->authRepository->revokeUserToken($user);

        $tokenName = 'auth_token_' . now()->timestamp;
        $token = $user->createToken($tokenName);
        $expiresIn = 24 * 60 * 60; // 24 horas

        $userWithRelations = $this->authRepository->findUserWithTokens($user->id);
        $loginResponse = AuthMapper::toLoginResponse($userWithRelations, $token->plainTextToken, $expiresIn);

        return ResponseHelper::success(
            AuthMapper::loginResponseToArray($loginResponse),
            'Token renovado exitosamente'
        );
    }

    public function revokeAllTokens($user): array
    {
        $this->logInfo('Revoking all user tokens', ['user_id' => $user->id]);
        $this->authRepository->revokeAllUserTokens($user);
        return ResponseHelper::success([], 'Todos los tokens han sido revocados');
    }

    public function changePassword($user, string $currentPassword, string $newPassword): array
    {
        $this->logInfo('Attempting password change', ['user_id' => $user->id]);

        if (!Hash::check($currentPassword, $user->password)) {
            return ResponseHelper::unauthorized('La contraseña actual es incorrecta');
        }

        $hashedNewPassword = Hash::make($newPassword);
        $this->authRepository->updateUserPassword($user->id, $hashedNewPassword);

        $this->logInfo('Password changed successfully', ['user_id' => $user->id]);

        return ResponseHelper::success([], 'Contraseña actualizada exitosamente');
    }
}
