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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents');
            $table->foreignId('user_id')->constrained('users');
            $table->text('description')->nullable();
            $table->string('code', 50)->unique();
            $table->foreignId('zone_id')->nullable()->constrained('zones');
            $table->foreignId('transaction_type_id')->constrained('transaction_types');
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->date('date');
            $table->decimal('total', 10, 2)->default(0);
            $table->foreignId('trip_id')->nullable()->constrained('trips');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['code']);
            $table->index(['date']);
            $table->index(['agent_id']);
            $table->index(['user_id']);
            $table->index(['zone_id']);
            $table->index(['transaction_type_id']);
            $table->index(['trip_id']);
            $table->index(['total']);
            $table->index(['amount_paid']);
            $table->index(['deleted_at']);
        });

        Schema::create('transaction_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('transaction_id')->constrained('transactions');
            $table->decimal('price', 10, 2);
            $table->decimal('quantity', 10, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id']);
            $table->index(['transaction_id']);
            $table->index(['price']);
            $table->index(['quantity']);
            $table->index(['deleted_at']);
            $table->unique(['product_id', 'transaction_id'], 'unique_product_transaction');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_details');
        Schema::dropIfExists('transactions');
    }
};
