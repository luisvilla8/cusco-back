<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoMedidaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('tipo_medidas')->insert([
            'nombre' => "Litro",
            'prefijo' => "lt",
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('tipo_medidas')->insert([
            'nombre' => "Kilogramo",
            'prefijo' => "kg",
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
