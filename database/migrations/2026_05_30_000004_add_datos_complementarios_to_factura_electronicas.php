<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factura_electronicas', function (Blueprint $table) {
            if (! Schema::hasColumn('factura_electronicas', 'sifen_api_documento_id')) {
                $table->unsignedBigInteger('sifen_api_documento_id')->nullable()->after('pdf_path')
                    ->comment('ID del documento en sifen-api');
            }
            if (! Schema::hasColumn('factura_electronicas', 'datos_complementarios')) {
                $table->json('datos_complementarios')->nullable()->after('observaciones')
                    ->comment('Campos SIFEN: NC/ND, autofactura, nota de remisión');
            }
        });
    }

    public function down(): void
    {
        Schema::table('factura_electronicas', function (Blueprint $table) {
            if (Schema::hasColumn('factura_electronicas', 'datos_complementarios')) {
                $table->dropColumn('datos_complementarios');
            }
            if (Schema::hasColumn('factura_electronicas', 'sifen_api_documento_id')) {
                $table->dropColumn('sifen_api_documento_id');
            }
        });
    }
};
