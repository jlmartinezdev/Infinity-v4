<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_mensajes', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_mensajes', 'contacto_nombre')) {
                $table->string('contacto_nombre', 200)->nullable()->after('telefono');
            }
        });

        Schema::create('whatsapp_contactos', function (Blueprint $table) {
            $table->id();
            $table->string('telefono', 20)->unique();
            $table->string('nombre', 200)->nullable();
            $table->unsignedInteger('cliente_id')->nullable()->index();
            $table->timestamp('ultimo_visto_at')->nullable()->index();
            $table->unsignedInteger('mensajes_count')->default(0);
            $table->timestamps();

            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contactos');

        Schema::table('whatsapp_mensajes', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_mensajes', 'contacto_nombre')) {
                $table->dropColumn('contacto_nombre');
            }
        });
    }
};
