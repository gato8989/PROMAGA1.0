// database/migrations/YYYY_MM_DD_add_recomendacion_enviada_to_clientes_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'recomendacion_enviada')) {
                $table->boolean('recomendacion_enviada')->default(false)->after('garantia_enviada');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('recomendacion_enviada');
        });
    }
};