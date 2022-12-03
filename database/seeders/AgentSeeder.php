<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('agents')->insert([
            "nombre" => "Cliente test 1",
            "telefono" => "987654321",
            "direccion" => "Calle 123",
            "email" => "cliente@gmail.com",
            "dni" => "12345678",
            "ruc" => "12345678901",
            "id_tipo_agente" => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('agents')->insert([
            "nombre" => "Proveedor test 2",
            "telefono" => "999999999",
            "direccion" => "Calle 456",
            "email" => "proveedor@gmail.com",
            "dni" => "87654321",
            "ruc" => "10987654321",
            "id_tipo_agente" => 2,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
