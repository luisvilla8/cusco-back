<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('usuarios')->insert([
            'nombre' => 'admin',
            'apellidos' => 'admin',
            'email' => 'luis888lfvl@gmail.com',
            'password' => "admin123",
            'id_tipo_usuario' => 1,
            'telefono' => '981018671',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
