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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('code')->unique();
            $table->string('barcode')->nullable();
            $table->string('image_url')->nullable();
            $table->decimal('stock', 15, 2)->default(0);
            $table->decimal('reserved_stock', 15, 2)->default(0); // ✅ NUEVO CAMPO
            $table->decimal('min_stock', 15, 2)->default(0);
            $table->decimal('max_stock', 15, 2)->default(0);
            $table->decimal('cost', 15, 2)->default(0);
            $table->decimal('price', 15, 2)->default(0);
            $table->foreignId('measure_type_id')->constrained();
            $table->foreignId('product_category_id')->constrained();
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('code');
            $table->index('barcode');
            $table->index(['measure_type_id', 'product_category_id']);
            $table->index('stock');
            $table->index(['stock', 'min_stock']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
};
