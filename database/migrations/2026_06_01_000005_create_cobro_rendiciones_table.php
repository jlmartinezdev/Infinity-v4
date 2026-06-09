<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobro_rendiciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('usuario_cobrador_id');
            $table->foreign('usuario_cobrador_id')->references('usuario_id')->on('users')->restrictOnDelete();
            $table->unsignedInteger('usuario_tesorero_id');
            $table->foreign('usuario_tesorero_id')->references('usuario_id')->on('users')->restrictOnDelete();
            $table->decimal('monto', 15, 2);
            $table->unsignedInteger('cantidad_cobros')->default(0);
            $table->dateTime('fecha_rendicion');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('fecha_rendicion');
            $table->index('usuario_cobrador_id');
        });

        Schema::table('cobros', function (Blueprint $table) {
            if (! Schema::hasColumn('cobros', 'cobro_rendicion_id')) {
                $table->foreignId('cobro_rendicion_id')->nullable()->after('usuario_id')
                    ->constrained('cobro_rendiciones')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cobros', function (Blueprint $table) {
            if (Schema::hasColumn('cobros', 'cobro_rendicion_id')) {
                $table->dropConstrainedForeignId('cobro_rendicion_id');
            }
        });

        Schema::dropIfExists('cobro_rendiciones');
    }
};
