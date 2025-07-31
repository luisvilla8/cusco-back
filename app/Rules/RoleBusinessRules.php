<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Rules\RoleBusinessRules.php

namespace App\Rules;

use App\Models\Role;

class RoleBusinessRules
{
   
    public static function canBeDeleted(Role $role): bool
    {
        return !$role->hasUsers() 
            && !self::isProtectedRole($role);
    }

 
    public static function isProtectedRole(Role $role): bool
    {
        return in_array($role->name, ['Administrador', 'Super Admin']) 
            || $role->id === 1;
    }

    
    public static function validateDeletion(Role $role): void
    {
        if ($role->hasUsers()) {
            throw new \InvalidArgumentException('No se puede eliminar un rol que tiene usuarios asignados');
        }
        
        if (self::isProtectedRole($role)) {
            throw new \InvalidArgumentException('No se puede eliminar este rol del sistema');
        }
    }

    public static function validateForceDeletion(Role $role): void
    {
        if ($role->hasUsersInHistory()) {
            throw new \InvalidArgumentException('No se puede eliminar permanentemente un rol que tiene historial de usuarios');
        }
    }
}