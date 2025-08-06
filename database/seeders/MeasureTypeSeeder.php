<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\database\seeders\MeasureTypeSeeder.php

namespace Database\Seeders;

use App\Models\MeasureType;
use Illuminate\Database\Seeder;

class MeasureTypeSeeder extends Seeder
{
    public function run(): void
    {
        $measureTypes = [
            [
                'name' => 'Unidad',
                'symbol' => 'Und',
                'description' => 'Productos vendidos por unidad individual',
            ],
            [
                'name' => 'Kilogramo',
                'symbol' => 'Kg',
                'description' => 'Productos vendidos por peso en kilogramos',
            ],
            [
                'name' => 'Gramo',
                'symbol' => 'g',
                'description' => 'Productos vendidos por peso en gramos',
            ],
            [
                'name' => 'Litro',
                'symbol' => 'L',
                'description' => 'Productos vendidos por volumen en litros',
            ],
            [
                'name' => 'Mililitro',
                'symbol' => 'mL',
                'description' => 'Productos vendidos por volumen en mililitros',
            ],
            [
                'name' => 'Metro',
                'symbol' => 'm',
                'description' => 'Productos vendidos por longitud en metros',
            ],
            [
                'name' => 'Centímetro',
                'symbol' => 'cm',
                'description' => 'Productos vendidos por longitud en centímetros',
            ],
            [
                'name' => 'Caja',
                'symbol' => 'Caja',
                'description' => 'Productos vendidos por caja',
            ],
            [
                'name' => 'Paquete',
                'symbol' => 'Paq',
                'description' => 'Productos vendidos por paquete',
            ],
            [
                'name' => 'Docena',
                'symbol' => 'Dz',
                'description' => 'Productos vendidos por docena (12 unidades)',
            ],
        ];

        foreach ($measureTypes as $measureType) {
            MeasureType::create($measureType);
        }
    }
}