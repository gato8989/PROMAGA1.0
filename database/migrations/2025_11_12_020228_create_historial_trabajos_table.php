<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_historial_trabajos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('historial_trabajos', function (Blueprint $table) {
            $table->id();
            $table->string('marca');
            $table->string('modelo');
            $table->string('año');
            $table->json('trabajos');
            $table->json('subtrabajos_estado')->nullable();
            $table->string('fecha_ingreso');
            $table->string('fecha_terminado');
            $table->string('usuario_termino'); // Nombre del usuario que terminó el trabajo
            $table->string('color')->default('#261472');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('historial_trabajos');
    }
};