<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\UserZone;
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
            $this->command->error('Roles not found. Available roles: ' . Role::pluck('name')->implode(', '));
            return;
        }

        // ✅ VERIFICAR ZONA
        if (!$zone) {
            $this->command->error('No zones found. Run ZoneSeeder first.');
            return;
        }

        $users = [
            [
                'name' => 'Luis Fernando Administrador',
                'email' => 'luis888lfvl@gmail.com',
                'password' => Hash::make('admin123'),
                'phone' => '981018671',
                'role_id' => $adminRole->id,
                // ❌ ELIMINAR zone_id
            ],
            [
                'name' => 'Juan Carlos Vendedor',
                'email' => 'vendedor@cuscoback.com',
                'password' => Hash::make('vendedor123'),
                'phone' => '987654321',
                'role_id' => $vendedorRole->id,
                // ❌ ELIMINAR zone_id
            ],
            [
                'name' => 'María Elena Supervisora',
                'email' => 'supervisor@cuscoback.com',
                'password' => Hash::make('supervisor123'),
                'phone' => '987654322',
                'role_id' => $supervisorRole->id,
                // ❌ ELIMINAR zone_id
            ],
            [
                'name' => 'Carlos Admin',
                'email' => 'admin@cuscoback.com',
                'password' => Hash::make('admin123'),
                'phone' => '987654323',
                'role_id' => $adminRole->id,
                // ❌ ELIMINAR zone_id
            ],
            [
                'name' => 'Admin Principal',
                'email' => 'admin@cusco.com',
                'password' => Hash::make('password123'),
                'phone' => '987654324', // ✅ CAMBIAR teléfono duplicado
                'role_id' => $adminRole->id,
                // ❌ ELIMINAR zone_id
            ],
            [
                'name' => 'Juan Pérez Agente',
                'email' => 'juan.perez@cusco.com',
                'password' => Hash::make('password123'),
                'phone' => '987654325', // ✅ CAMBIAR teléfono duplicado
                'role_id' => $vendedorRole->id,
                // ❌ ELIMINAR zone_id
            ],
        ];

        foreach ($users as $userData) {
            try {
                $user = User::updateOrCreate(
                    ['email' => $userData['email']],
                    $userData
                );
                
                // ✅ ASIGNAR zona a través de UserZone - ESPECIALMENTE IMPORTANTE PARA VENDEDORES
                if ($zone && !$user->hasZone($zone->id)) {
                    UserZone::create([
                        'user_id' => $user->id,
                        'zone_id' => $zone->id
                    ]);
                }
                
                // ✅ LOG DETALLADO PARA VERIFICAR PERMISOS - CORREGIR SINTAXIS
                $roleName = $user->role ? $user->role->name : 'Sin rol';
                $this->command->info("✅ Usuario creado: {$user->name} - Rol: {$roleName}");
                $this->command->info("   📍 Zonas asignadas: " . $user->zones->pluck('name')->implode(', '));
                
            } catch (\Exception $e) {
                $this->command->error("❌ Error creando usuario {$userData['email']}: " . $e->getMessage());
            }
        }
    }
}
