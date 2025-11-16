<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_notas_to_historial_trabajos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('historial_trabajos', function (Blueprint $table) {
            $table->text('notas')->nullable()->after('color');
        });
    }

    public function down()
    {
        Schema::table('historial_trabajos', function (Blueprint $table) {
            $table->dropColumn('notas');
        });
    }
};