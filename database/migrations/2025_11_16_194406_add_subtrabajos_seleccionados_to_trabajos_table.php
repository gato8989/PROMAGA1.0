<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_subtrabajos_seleccionados_to_trabajos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('trabajos', function (Blueprint $table) {
            $table->json('subtrabajos_seleccionados')->nullable()->after('subtrabajos_estado');
        });
    }

    public function down()
    {
        Schema::table('trabajos', function (Blueprint $table) {
            $table->dropColumn('subtrabajos_seleccionados');
        });
    }
};