<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_trabajos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('trabajos', function (Blueprint $table) {
            $table->id();
            $table->string('marca');
            $table->string('modelo');
            $table->string('año');
            $table->json('trabajos'); // Array de trabajos principales
            $table->json('subtrabajos_estado')->nullable(); // REMOVED default value
            $table->string('fecha_ingreso');
            $table->string('color')->default('#261472');
            $table->boolean('completado')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('trabajos');
    }
};