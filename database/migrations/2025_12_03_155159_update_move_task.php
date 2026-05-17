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
        Schema::table('move_tasks', function (Blueprint $table) {
            $table->string('from')->nullable()->after("progress");
            $table->string('to')->nullable()->after("from");
            $table->dropColumn('dest_path');
                        
            $table->unsignedBigInteger('copied_bytes')->default(0)->after("to");
            $table->unsignedBigInteger('total_bytes')->default(0)->after("copied_bytes");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('move_tasks', function (Blueprint $table) {
            $table->dropColumn('from');
            $table->dropColumn('to');
            $table->string('dest_path');
            $table->dropColumn('copied_bytes');
            $table->dropColumn('total_bytes');
        });
    }
};
