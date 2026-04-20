<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('factura_interna_id')->nullable()->after('fecha_cierre')
                ->constrained('factura_internas')->nullOnDelete();
            $table->decimal('monto_cobro_ticket', 15, 2)->nullable()->after('factura_interna_id');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['factura_interna_id']);
            $table->dropColumn(['factura_interna_id', 'monto_cobro_ticket']);
        });
    }
};
