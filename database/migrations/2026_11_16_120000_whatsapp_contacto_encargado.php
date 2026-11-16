<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_contactos', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_contactos', 'encargado_usuario_id')) {
                $table->unsignedInteger('encargado_usuario_id')->nullable()->after('whatsapp_asunto_id');
                $table->timestamp('encargado_at')->nullable()->after('encargado_usuario_id');
                $table->foreign('encargado_usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_contactos', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_contactos', 'encargado_usuario_id')) {
                $table->dropForeign(['encargado_usuario_id']);
                $table->dropColumn(['encargado_usuario_id', 'encargado_at']);
            }
        });
    }
};
