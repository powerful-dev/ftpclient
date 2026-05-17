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
        Schema::dropIfExists('copy_tasks');
        Schema::dropIfExists('delete_tasks');
        Schema::dropIfExists('move_tasks');
        Schema::dropIfExists('download_tasks');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
