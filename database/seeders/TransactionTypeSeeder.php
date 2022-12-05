<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('transaction_types')->insert([
            "nombre" => "Venta",
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('transaction_types')->insert([
            "nombre" => "Compra",
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
