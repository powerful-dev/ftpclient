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
        Schema::table('delete_tasks', function (Blueprint $table) {
            //$table->dropColumn(['status', 'progress']);
            $table->integer('total_files')->default(0)->after('task_id');
            $table->integer('deleted_files')->default(0)->after('total_files');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delete_tasks', function (Blueprint $table) {
            $table->dropColumn(['total_files', 'deleted_files']);
            //$table->string('status')->default('running')->after('task_id');
            //$table->double('progress')->default(0)->after('status');
        });
    }
};
