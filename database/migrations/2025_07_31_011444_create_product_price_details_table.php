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
        Schema::create('product_price_details', function (Blueprint $table) {
            $table->id();
            $table->decimal('price', 10, 2);
            $table->string('code', 50)->unique();
            $table->foreignId('zone_id')->constrained('zones');
            $table->foreignId('product_id')->constrained('products');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['price']);
            $table->index(['code']);
            $table->index(['zone_id']);
            $table->index(['product_id']);
            $table->index(['deleted_at']);
            $table->unique(['zone_id', 'product_id'], 'unique_zone_product');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_price_details');
    }
};
