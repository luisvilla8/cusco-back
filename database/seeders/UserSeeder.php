<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener roles
        $adminRole = Role::where('name', 'Administrador')->first();
        $vendedorRole = Role::where('name', 'Vendedor')->first();
        $supervisorRole = Role::where('name', 'Supervisor')->first();
        $agentRole = Role::where('name', 'Agente')->first();
        $zone = Zone::first();

        // Verificar que existen los roles
        if (!$adminRole || !$vendedorRole || !$supervisorRole || !$agentRole) {
            $this->command->error('Roles not found. Run RoleSeeder first.');
            return;
        }

        $users = [
            [
                'name' => 'Luis Fernando Administrador',
                'email' => 'luis888lfvl@gmail.com',
                'password' => Hash::make('admin123'),
                'phone' => '981018671',
                'role_id' => $adminRole->id,
            ],
            [
                'name' => 'Juan Carlos Vendedor',
                'email' => 'vendedor@cuscoback.com',
                'password' => Hash::make('vendedor123'),
                'phone' => '987654321',
                'role_id' => $vendedorRole->id,
            ],
            [
                'name' => 'María Elena Supervisora',
                'email' => 'supervisor@cuscoback.com',
                'password' => Hash::make('supervisor123'),
                'phone' => '987654322',
                'role_id' => $supervisorRole->id,
            ],
            [
                'name' => 'Carlos Admin',
                'email' => 'admin@cuscoback.com',
                'password' => Hash::make('admin123'),
                'phone' => '987654323',
                'role_id' => $adminRole->id,
            ],
            [
                'name' => 'Admin Principal',
                'email' => 'admin@cusco.com',
                'password' => Hash::make('password123'),
                'phone' => '987654321',
                'role_id' => $adminRole?->id,
                'zone_id' => $zone?->id,
            ],
            [
                'name' => 'Juan Pérez',
                'email' => 'juan.perez@cusco.com',
                'password' => Hash::make('password123'),
                'phone' => '987654322',
                'role_id' => $agentRole?->id,
                'zone_id' => $zone?->id,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
