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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('telefono');
            $table->foreignId('historial_trabajo_id')
                  ->nullable()
                  ->constrained('historial_trabajos')
                  ->nullOnDelete();
            $table->date('ultima_visita')->nullable();
            $table->boolean('recordatorio_enviado')->default(false);
            $table->date('ultimo_recordatorio')->nullable();
            $table->boolean('finalizacion_enviada')->default(false);
            $table->boolean('garantia_enviada')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};