<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('productos')->insert([
            'nombre' => "BAZUKA",
            'id_tipo_medida' => 1,
            'descripcion' => "Herbicida sistémico, post – emergente no selectivo. Controla eficazmente malezas gramíneas, ciperáceas y de hoja ancha, tanto anuales como perennes en bordes de palto, mango, cítricos, café, palma aceitera, caña de azúcar, etc.",
            'cantidad' => 0,
            'costo' => 00.00,
            'precio' => 00.00,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('productos')->insert([
            'nombre' => "ERRASER",
            'id_tipo_medida' => 2,
            'descripcion' => "Herbicida solicitado por los agricultores por su eficiente y prolongado control. Su alta concentración facilita su manejo y transporte.",
            'cantidad' => 0,
            'costo' => 00.00,
            'precio' => 00.00,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
