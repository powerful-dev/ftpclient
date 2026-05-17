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
        Schema::create('column_widths', function (Blueprint $table) {
            $table->id();
            $table->string('panel');
            $table->string('column'); 
            $table->integer('width'); 
            $table->timestamps();
            $table->unique(['panel', 'column']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('column_widths');
    }
};
