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
                'is_active' => true,
            ],
            [
                'name' => 'Kilogramo',
                'symbol' => 'Kg',
                'description' => 'Productos vendidos por peso en kilogramos',
                'is_active' => true,
            ],
            [
                'name' => 'Gramo',
                'symbol' => 'g',
                'description' => 'Productos vendidos por peso en gramos',
                'is_active' => true,
            ],
            [
                'name' => 'Litro',
                'symbol' => 'L',
                'description' => 'Productos vendidos por volumen en litros',
                'is_active' => true,
            ],
            [
                'name' => 'Mililitro',
                'symbol' => 'mL',
                'description' => 'Productos vendidos por volumen en mililitros',
                'is_active' => true,
            ],
            [
                'name' => 'Metro',
                'symbol' => 'm',
                'description' => 'Productos vendidos por longitud en metros',
                'is_active' => true,
            ],
            [
                'name' => 'Centímetro',
                'symbol' => 'cm',
                'description' => 'Productos vendidos por longitud en centímetros',
                'is_active' => true,
            ],
            [
                'name' => 'Caja',
                'symbol' => 'Caja',
                'description' => 'Productos vendidos por caja',
                'is_active' => true,
            ],
            [
                'name' => 'Paquete',
                'symbol' => 'Paq',
                'description' => 'Productos vendidos por paquete',
                'is_active' => true,
            ],
            [
                'name' => 'Docena',
                'symbol' => 'Dz',
                'description' => 'Productos vendidos por docena (12 unidades)',
                'is_active' => true,
            ],
        ];

        foreach ($measureTypes as $measureType) {
            MeasureType::create($measureType);
        }
    }
}