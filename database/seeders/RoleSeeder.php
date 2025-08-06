<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Administrador',
                'description' => 'Acceso completo al sistema'
                // code se genera automáticamente: "ADMIN"
            ],
            [
                'name' => 'Vendedor',  // ✅ AGREGAR ESTE ROL
                'description' => 'Personal de ventas en tienda'
                // code se genera automáticamente: "VEND"
            ],
            [
                'name' => 'Agente',
                'description' => 'Agente de ventas en campo'
                // code se genera automáticamente: "AGENT"
            ],
            [
                'name' => 'Usuario',
                'description' => 'Usuario básico del sistema'
                // code se genera automáticamente: "USER"
            ],
            [
                'name' => 'Supervisor',
                'description' => 'Supervisor de operaciones'
                // code se genera automáticamente: "SUPER"
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']], // ✅ Evitar duplicados
                $role
            );
        }
    }
}