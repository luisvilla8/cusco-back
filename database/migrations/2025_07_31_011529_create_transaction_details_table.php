<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // ✅ CREAR transaction_details SIN CONSTRAINT ÚNICO
        Schema::create('transaction_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('transaction_id')->constrained('transactions');
            $table->decimal('price', 10, 2);
            $table->decimal('quantity', 10, 2);
            $table->timestamps();
            $table->softDeletes();

            // ✅ ÍNDICES NORMALES PARA PERFORMANCE
            $table->index(['product_id']);
            $table->index(['transaction_id']);
            $table->index(['price']);
            $table->index(['quantity']);
            $table->index(['deleted_at']);
            
            // ✅ ÍNDICE COMPUESTO NORMAL (NO ÚNICO) PARA BÚSQUEDAS RÁPIDAS
            $table->index(['product_id', 'transaction_id'], 'idx_product_transaction');
            
            // ❌ ELIMINADO: unique constraint que causaba problemas
            // $table->unique(['product_id', 'transaction_id'], 'unique_product_transaction');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // ✅ ELIMINAR transaction_details
        Schema::dropIfExists('transaction_details');
    }
};