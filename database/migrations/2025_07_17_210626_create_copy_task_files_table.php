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
        Schema::create('copy_task_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('task_id');
            $table->string('file_name');
            $table->string('path');
            $table->enum('status', ['pending', 'copying', 'copied', 'failed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('copy_task_files');
    }
};
