<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_mensajes', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_mensajes', 'usuario_id')) {
                $table->unsignedInteger('usuario_id')->nullable()->after('cliente_id');
                $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_mensajes', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_mensajes', 'usuario_id')) {
                $table->dropForeign(['usuario_id']);
                $table->dropColumn('usuario_id');
            }
        });
    }
};
