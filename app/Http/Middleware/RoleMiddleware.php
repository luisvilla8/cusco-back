<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Middleware\RoleMiddleware.php

namespace App\Http\Middleware;

use App\Attributes\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use ReflectionClass;
use ReflectionMethod;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        // Obtener el controlador y método actual
        $route = $request->route();
        $controller = $route->getController();
        $method = $route->getActionMethod();

        if (!$controller || !$method) {
            return $next($request);
        }

        // Verificar permisos por atributos
        $hasPermission = $this->checkRolePermissions($controller, $method, $user);

        if (!$hasPermission['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $hasPermission['message'],
                'required_roles' => $hasPermission['required_roles'] ?? []
            ], 403);
        }

        return $next($request);
    }

    private function checkRolePermissions($controller, string $method, $user): array
    {
        try {
            $reflection = new ReflectionClass($controller);
            $methodReflection = $reflection->getMethod($method);

            // 1. Verificar atributo en el método específico (prioridad alta)
            $methodAttributes = $methodReflection->getAttributes(Role::class);
            if (!empty($methodAttributes)) {
                return $this->evaluateRoleAttribute($methodAttributes[0]->newInstance(), $user);
            }

            // 2. Verificar atributo en la clase (prioridad baja)
            $classAttributes = $reflection->getAttributes(Role::class);
            if (!empty($classAttributes)) {
                return $this->evaluateRoleAttribute($classAttributes[0]->newInstance(), $user);
            }

            // 3. Sin atributos = acceso permitido
            return ['allowed' => true];

        } catch (\Exception $e) {
            \Log::error('Error checking role permissions', [
                'controller' => get_class($controller),
                'method' => $method,
                'error' => $e->getMessage()
            ]);

            return [
                'allowed' => false,
                'message' => 'Error interno de autorización'
            ];
        }
    }

    private function evaluateRoleAttribute(Role $roleAttribute, $user): array
    {
        $requiredRoles = $roleAttribute->getRoles();
        $userRole = $user->getRoleName();

        if (!$userRole) {
            return [
                'allowed' => false,
                'message' => 'Usuario sin rol asignado',
                'required_roles' => $requiredRoles
            ];
        }

        // Verificar si el usuario tiene los roles requeridos
        if ($roleAttribute->requiresAllRoles()) {
            // Modo AND: debe tener TODOS los roles
            $hasAllRoles = $user->hasAnyRole($requiredRoles) && count($requiredRoles) === 1 
                         ? true 
                         : collect($requiredRoles)->every(fn($role) => $user->hasRole($role));
            
            if (!$hasAllRoles) {
                return [
                    'allowed' => false,
                    'message' => $roleAttribute->getMessage(),
                    'required_roles' => $requiredRoles,
                    'user_role' => $userRole,
                    'mode' => 'ALL_REQUIRED'
                ];
            }
        } else {
            // Modo OR: debe tener AL MENOS UNO de los roles
            if (!$user->hasAnyRole($requiredRoles)) {
                return [
                    'allowed' => false,
                    'message' => $roleAttribute->getMessage(),
                    'required_roles' => $requiredRoles,
                    'user_role' => $userRole,
                    'mode' => 'ANY_REQUIRED'
                ];
            }
        }

        return ['allowed' => true];
    }
}