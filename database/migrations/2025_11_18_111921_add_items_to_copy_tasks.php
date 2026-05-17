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
            $table->unsignedInteger('total_items')->default(0)->after('total_bytes');
            $table->unsignedInteger('copied_items')->default(0)->after('total_items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('copy_tasks', function (Blueprint $table) {
            $table->dropColumn(['total_items', 'copied_items']);
        });
    }
};
