<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // $this->call(UserSeeder::class);
        // $this->call(AgentTypeSeeder::class);
        // $this->call(AgentSeeder::class);
        // $this->call(ProductSeeder::class);
        // $this->call(TransactionTypeSeeder::class);

        $this->call([
            RoleSeeder::class,          // ✅ PRIMERO los roles
            UserSeeder::class,          // ✅ DESPUÉS los usuarios
            AgentTypesSeeder::class,    // ✅ Tipos de agente
            AgentSeeder::class,         // ✅ Agentes
            MeasureTypeSeeder::class,   // ✅ Tipos de medida
            ProductCategorySeeder::class, // ✅ Categorías de productos
            ZoneSeeder::class,          // ✅ Zonas
        ]);
    }
}
