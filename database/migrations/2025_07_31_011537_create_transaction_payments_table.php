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
        Schema::create('transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions');
            $table->decimal('amount_paid', 10, 2);
            $table->string('code', 50)->unique();
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['transaction_id']);
            $table->index(['amount_paid']);
            $table->index(['code']);
            $table->index(['payment_method_id']);
            $table->index(['deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_payments');
    }
};
