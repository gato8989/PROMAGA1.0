<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('historial_trabajos', function (Blueprint $table) {
            $table->string('hora_terminado')->nullable()->after('fecha_terminado');
            $table->string('hora_creacion')->nullable()->after('fecha_ingreso');
        });
    }

    public function down()
    {
        Schema::table('historial_trabajos', function (Blueprint $table) {
            $table->dropColumn(['hora_terminado', 'hora_creacion']);
        });
    }
};