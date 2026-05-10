// database/migrations/YYYY_MM_DD_XXXXXX_add_cliente_fields_to_trabajos_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajos', function (Blueprint $table) {
            if (!Schema::hasColumn('trabajos', 'cliente_nombre')) {
                $table->string('cliente_nombre')->nullable()->after('notas');
            }
            if (!Schema::hasColumn('trabajos', 'cliente_telefono')) {
                $table->string('cliente_telefono')->nullable()->after('cliente_nombre');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trabajos', function (Blueprint $table) {
            $table->dropColumn(['cliente_nombre', 'cliente_telefono']);
        });
    }
};