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
        Schema::table('historial_trabajos', function (Blueprint $table) {
            $table->json('subtrabajos_seleccionados')->nullable()->after('subtrabajos_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('historial_trabajos', function (Blueprint $table) {
            $table->dropColumn('subtrabajos_seleccionados');
        });
    }
};