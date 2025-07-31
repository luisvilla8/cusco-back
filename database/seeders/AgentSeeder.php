<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentType;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener tipos de agente
        $clienteType = AgentType::where('name', 'Cliente')->first();
        $proveedorType = AgentType::where('name', 'Proveedor')->first();

        $agents = [
            // CLIENTES
            [
                'name' => 'Comercial San Pedro EIRL',
                'phone' => '984123456',
                'address' => 'Av. El Sol 123, Mercado San Pedro, Cusco',
                'email' => 'ventas@comercialsanpedro.com',
                'dni' => null,
                'ruc' => '20123456789',
                'agent_type_id' => $clienteType->id,
            ],
            [
                'name' => 'Juan Carlos Pérez Mamani',
                'phone' => '987654321',
                'address' => 'Jr. Saphi 456, Centro Histórico, Cusco',
                'email' => 'juancarlos.perez@gmail.com',
                'dni' => '12345678',
                'ruc' => null,
                'agent_type_id' => $clienteType->id,
            ],
            [
                'name' => 'Restaurante Inka Grill SAC',
                'phone' => '984567890',
                'address' => 'Portal de Panes 115, Plaza de Armas, Cusco',
                'email' => 'compras@inkagrill.com',
                'dni' => null,
                'ruc' => '20234567890',
                'agent_type_id' => $clienteType->id,
            ],
            [
                'name' => 'María Elena Quispe Huamán',
                'phone' => '987654322',
                'address' => 'Av. La Cultura 789, Wanchaq, Cusco',
                'email' => 'maria.quispe@hotmail.com',
                'dni' => '23456789',
                'ruc' => null,
                'agent_type_id' => $clienteType->id,
            ],
            [
                'name' => 'Minimarket Los Andes',
                'phone' => '984678901',
                'address' => 'Av. Tullumayo 234, San Sebastián, Cusco',
                'email' => 'minimarket.losandes@gmail.com',
                'dni' => null,
                'ruc' => '20345678901',
                'agent_type_id' => $clienteType->id,
            ],

            // PROVEEDORES
            [
                'name' => 'Distribuidora Cusco SAC',
                'phone' => '984111222',
                'address' => 'Av. La Cultura 1001, Parque Industrial, Cusco',
                'email' => 'ventas@distribuidoracusco.com',
                'dni' => null,
                'ruc' => '20456789012',
                'agent_type_id' => $proveedorType->id,
            ],
            [
                'name' => 'Alimentos del Valle EIRL',
                'phone' => '984333444',
                'address' => 'Jr. Ayacucho 567, Santiago, Cusco',
                'email' => 'gerencia@alimentosdelvalle.com',
                'dni' => null,
                'ruc' => '20567890123',
                'agent_type_id' => $proveedorType->id,
            ],
            [
                'name' => 'Carlos Alberto Condori',
                'phone' => '987654323',
                'address' => 'Comunidad de Maras, Urubamba, Cusco',
                'email' => 'carlos.condori@outlook.com',
                'dni' => '34567890',
                'ruc' => '10345678901',
                'agent_type_id' => $proveedorType->id,
            ],
            [
                'name' => 'Productos Andinos del Sur SAC',
                'phone' => '984555666',
                'address' => 'Av. Regional 890, San Jerónimo, Cusco',
                'email' => 'compras@productosandinos.com',
                'dni' => null,
                'ruc' => '20678901234',
                'agent_type_id' => $proveedorType->id,
            ],
            [
                'name' => 'Ana Rosa Huamán Flores',
                'phone' => '987654324',
                'address' => 'Distrito de Pisaq, Valle Sagrado, Cusco',
                'email' => 'ana.huaman@gmail.com',
                'dni' => '45678901',
                'ruc' => '10456789012',
                'agent_type_id' => $proveedorType->id,
            ],
            [
                'name' => 'María González',
                'phone' => '987654325',
                'email' => 'maria.gonzalez@email.com',
                'dni' => '56789012',
                'address' => 'Av. El Sol 789, Cusco',
                'agent_type_id' => $clienteType?->id,
            ],
            [
                'name' => 'Empresa ABC SAC',
                'phone' => '084123457',
                'email' => 'contacto@abcsac.com',
                'ruc' => '20789012345',
                'address' => 'Jr. Comercio 789, Cusco',
                'agent_type_id' => $proveedorType?->id,
            ],
        ];

        foreach ($agents as $agent) {
            Agent::create($agent);
        }
    }
}
