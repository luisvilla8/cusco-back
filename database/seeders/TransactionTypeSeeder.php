<?php

namespace Database\Seeders;

use App\Models\TransactionType;
use Illuminate\Database\Seeder;

class TransactionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $transactionTypes = [
            [
                'name' => 'Venta',
                'code' => 'SALE',
                'description' => 'Transacción de venta de productos a clientes'
            ],
            [
                'name' => 'Compra', 
                'code' => 'PURCHASE',
                'description' => 'Transacción de compra de productos a proveedores'
            ],
            [
                'name' => 'Devolución de Venta',
                'code' => 'RETURN_SALE',
                'description' => 'Devolución de productos vendidos por parte del cliente'
            ],
            [
                'name' => 'Devolución de Compra',
                'code' => 'RETURN_PURCHASE', 
                'description' => 'Devolución de productos comprados al proveedor'
            ],
        ];

        foreach ($transactionTypes as $transactionType) {
            TransactionType::updateOrCreate(
                ['code' => $transactionType['code']],
                $transactionType
            );
        }

        $this->command->info('✅ TransactionTypes con devoluciones creados exitosamente');
    }
}