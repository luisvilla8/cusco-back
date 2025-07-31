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
        Schema::create('user_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('zone_id')->constrained('zones');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id']);
            $table->index(['zone_id']);
            $table->index(['deleted_at']);
            $table->unique(['user_id', 'zone_id'], 'unique_user_zone');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_zones');
    }
};
