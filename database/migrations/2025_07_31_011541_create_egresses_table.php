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
        Schema::create('egresses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->foreignId('agent_id')->nullable()->constrained('agents');
            $table->foreignId('trip_id')->nullable()->constrained('trips');
            $table->foreignId('zone_id')->nullable()->constrained('zones');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['code']);
            $table->index(['name']);
            $table->index(['amount']);
            $table->index(['date']);
            $table->index(['agent_id']);
            $table->index(['trip_id']);
            $table->index(['zone_id']);
            $table->index(['transaction_id']);
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
        Schema::dropIfExists('egresses');
    }
};
