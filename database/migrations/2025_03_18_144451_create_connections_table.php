<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable()->default('#3592C4');
            $table->string('host');
            $table->integer('port')->nullable();
            $table->integer('protocol_id'); 
            $table->integer('authentication_type_id'); 
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->text('ssh_key')->nullable();
            $table->string('last_left_path')->nullable();
            $table->string('last_right_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
