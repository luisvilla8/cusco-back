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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents');
            $table->foreignId('zone_id')->constrained('zones');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('travel_expenses', 10, 2)->default(0);
            $table->string('code', 50)->unique();
            $table->date('date_start');
            $table->date('date_end');
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name']);
            $table->index(['code']);
            $table->index(['date_start']);
            $table->index(['date_end']);
            $table->index(['agent_id']);
            $table->index(['zone_id']);
            $table->index(['total']);
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
        Schema::dropIfExists('trips');
    }
};
