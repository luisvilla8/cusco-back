<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('agent_types')->insert([
            "nombre" => "Cliente",
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('agent_types')->insert([
            "nombre" => "Proveedor",
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
