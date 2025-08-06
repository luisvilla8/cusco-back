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
            
            $table->foreignId('relation_to')->nullable()->constrained('transactions')
                  ->comment('Reference to original transaction for returns');
            
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->date('date');
            $table->decimal('total', 10, 2)->default(0);
            $table->foreignId('trip_id')->nullable()->constrained('trips');
            
            $table->enum('delivery_status', ['PENDING', 'DELIVERED', 'RETURNED', 'CANCELLED'])
                  ->default('PENDING')
                  ->comment('Delivery status of the transaction');
            $table->enum('payment_status', ['PENDING', 'PARTIAL', 'PAID', 'CANCELLED'])
                  ->default('PENDING')
                  ->comment('Payment status of the transaction');
            
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
            $table->index(['delivery_status']);
            $table->index(['payment_status']);
            $table->index(['delivery_status', 'payment_status']);
            $table->index(['relation_to']);
            $table->index(['transaction_type_id', 'relation_to']);
            $table->index(['delivery_status', 'relation_to']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
