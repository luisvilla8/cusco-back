<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Attributes\Role.php

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Role
{
    public array $roles;
    public string $message;
    public bool $requireAll;

    public function __construct(
        array|string $roles, 
        string $message = 'No tienes permisos para acceder a este recurso',
        bool $requireAll = false // false = OR (cualquier rol), true = AND (todos los roles)
    ) {
        $this->roles = is_array($roles) ? $roles : [$roles];
        $this->message = $message;
        $this->requireAll = $requireAll;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function requiresAllRoles(): bool
    {
        return $this->requireAll;
    }
}