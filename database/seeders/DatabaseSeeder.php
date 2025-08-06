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
        $this->call([
            ZoneSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            AgentTypesSeeder::class,
            AgentSeeder::class,
            MeasureTypeSeeder::class,
            ProductCategorySeeder::class,
            TransactionTypeSeeder::class,
            PaymentMethodSeeder::class,
        ]);
    }
}
