<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\database\seeders\ZoneSeeder.php

namespace Database\Seeders;

use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    public function run()
    {
        $zones = [
            [
                'name' => 'Centro',
                'description' => 'Zona céntrica de Cusco',
                'location_url' => '-13.5319,-71.9675'
                // code se genera automáticamente: "CENTRO01"
            ],
            [
                'name' => 'Norte',
                'description' => 'Zona norte de la ciudad',
                'location_url' => '-13.5200,-71.9600'
                // code se genera automáticamente: "NORTE01"
            ],
            [
                'name' => 'Sur',
                'description' => 'Zona sur de la ciudad',
                'location_url' => '-13.5400,-71.9700'
                // code se genera automáticamente: "SUR01"
            ],
        ];

        foreach ($zones as $zone) {
            Zone::create($zone);
        }
    }
}