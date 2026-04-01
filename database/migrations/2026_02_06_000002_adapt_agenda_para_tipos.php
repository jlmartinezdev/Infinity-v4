<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda', function (Blueprint $table) {
            $table->string('tipo', 20)->default('pedido')->after('id')
                ->comment('pedido = cita de instalación, general = otro tipo');
            $table->string('titulo', 120)->nullable()->after('tipo');
        });

        Schema::table('agenda', function (Blueprint $table) {
            $table->dropForeign(['pedido_id']);
        });

        Schema::table('agenda', function (Blueprint $table) {
            $table->unsignedInteger('pedido_id')->nullable()->change();
            $table->foreign('pedido_id')->references('pedido_id')->on('pedidos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agenda', function (Blueprint $table) {
            $table->dropForeign(['pedido_id']);
        });

        Schema::table('agenda', function (Blueprint $table) {
            $table->unsignedInteger('pedido_id')->nullable(false)->change();
            $table->foreign('pedido_id')->references('pedido_id')->on('pedidos');
        });

        Schema::table('agenda', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'titulo']);
        });
    }
};
