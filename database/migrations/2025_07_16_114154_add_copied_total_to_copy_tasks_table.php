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
        Schema::table('copy_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('copied_bytes')->default(0)->after("status");
            $table->unsignedBigInteger('total_bytes')->default(0)->after("copied_bytes");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('copy_tasks', function (Blueprint $table) {
            //
        });
    }
};
