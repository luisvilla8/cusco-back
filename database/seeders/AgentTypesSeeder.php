<?php

namespace Database\Seeders;

use App\Models\AgentType;
use Illuminate\Database\Seeder;

class AgentTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $agentTypes = [
            [
                'name' => 'Cliente',
                'description' => 'Cliente final'
                // code se genera automáticamente: "CLIENT"
            ],
            [
                'name' => 'Proveedor',
                'description' => 'Proveedor de productos'
                // code se genera automáticamente: "PROV"
            ],
            [
                'name' => 'Distribuidor',
                'description' => 'Distribuidor mayorista'
                // code se genera automáticamente: "DIST"
            ],
        ];

        foreach ($agentTypes as $agentType) {
            AgentType::create($agentType);
        }
    }
}
