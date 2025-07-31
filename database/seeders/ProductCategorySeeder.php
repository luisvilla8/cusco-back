<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\database\seeders\ProductCategorySeeder.php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'name' => 'Bebidas',
                'description' => 'Bebidas en general'
                // code se genera automáticamente
            ],
            [
                'name' => 'Comestibles',
                'description' => 'Productos alimenticios'
                // code se genera automáticamente
            ],
            [
                'name' => 'Limpieza',
                'description' => 'Productos de limpieza'
                // code se genera automáticamente
            ],
        ];

        foreach ($categories as $category) {
            ProductCategory::create($category);
        }
    }
}