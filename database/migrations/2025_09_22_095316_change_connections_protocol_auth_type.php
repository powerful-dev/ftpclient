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
        Schema::table('connections', function (Blueprint $table) {
            $table->string('protocol_id')->change();
            $table->string('authentication_type_id')->change();

            $table->renameColumn('protocol_id', 'protocol');
            $table->renameColumn('authentication_type_id', 'authentication_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->unsignedBigInteger('protocol_id')->change();
            $table->unsignedBigInteger('authentication_type_id')->change();

            $table->unsignedBigInteger('protocol_id')->change();
            $table->unsignedBigInteger('authentication_type_id')->change();
        });
    }
};
