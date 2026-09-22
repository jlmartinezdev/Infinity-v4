<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'proveedor_id')) {
                $table->foreignId('proveedor_id')->nullable()->after('categoria_id')->constrained('proveedores')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'proveedor_id')) {
                $table->dropConstrainedForeignId('proveedor_id');
            }
        });
    }
};
