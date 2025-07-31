<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('products')->insert([
            'name' => "BAZUKA",
            'measure_type_id' => 1,
            'description' => "Herbicida sistémico, post – emergente no selectivo. Controla eficazmente malezas gramíneas, ciperáceas y de hoja ancha, tanto anuales como perennes en bordes de palto, mango, cítricos, café, palma aceitera, caña de azúcar, etc.",
            'quantity' => 0,
            'cost' => 20.00,
            'price' => 25.00,
            'image_url' => 'https://www.agrovetmarket.com.pe/wp-content/uploads/2019/10/BAZUKA-1.jpg',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('products')->insert([
            'name' => "ERRASER",
            'measure_type_id' => 2,
            'description' => "Herbicida solicitado por los agricultores por su eficiente y prolongado control. Su alta concentración facilita su manejo y transporte.",
            'quantity' => 0,
            'cost' => 35.00,
            'price' => 40.00,
            'image_url' => 'https://www.agrovetmarket.com.pe/wp-content/uploads/2019/10/ERRASER-1.jpg',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
