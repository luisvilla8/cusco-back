<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            [
                'name' => 'Efectivo',
                'description' => 'Pago en efectivo - dinero físico'
                // code se genera automáticamente: "CASH"
            ],
            [
                'name' => 'Tarjeta de Crédito',
                'description' => 'Pago con tarjeta de crédito Visa, MasterCard, etc.'
                // code se genera automáticamente: "CARD"
            ],
            [
                'name' => 'Tarjeta de Débito',
                'description' => 'Pago con tarjeta de débito'
                // code se genera automáticamente: "DEBIT"
            ],
            [
                'name' => 'Transferencia Bancaria',
                'description' => 'Transferencia electrónica entre cuentas bancarias'
                // code se genera automáticamente: "TRANSFER"
            ],
            [
                'name' => 'Yape',
                'description' => 'Pago mediante aplicación Yape del BCP'
                // code se genera automáticamente: "YAPE"
            ],
            [
                'name' => 'Plin',
                'description' => 'Pago mediante aplicación Plin'
                // code se genera automáticamente: "PLIN"
            ],
            [
                'name' => 'Billetera Digital',
                'description' => 'Otros métodos de pago digital'
                // code se genera automáticamente: "WALLET"
            ],
            [
                'name' => 'Depósito Bancario',
                'description' => 'Depósito directo en cuenta bancaria'
                // code se genera automáticamente: "DEPOSIT"
            ],
        ];

        foreach ($paymentMethods as $paymentMethod) {
            PaymentMethod::updateOrCreate(
                ['name' => $paymentMethod['name']], // ✅ Evitar duplicados
                $paymentMethod
            );
        }

        $this->command->info('✅ PaymentMethods creados exitosamente');
    }
}