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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('code', 50)->unique();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('email', 255)->nullable();
            $table->string('dni', 20)->nullable()->unique();
            $table->string('ruc', 20)->nullable()->unique();
            $table->foreignId('agent_type_id')->constrained('agent_types');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name']);
            $table->index(['code']);
            $table->index(['email']);
            $table->index(['dni']);
            $table->index(['ruc']);
            $table->index(['phone']);
            $table->index(['agent_type_id']);
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
        Schema::dropIfExists('agents');
    }
};
