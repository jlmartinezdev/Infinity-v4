<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_egreso_plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 160)->unique();
            $table->decimal('monto', 15, 2);
            $table->boolean('es_sueldo')->default(false);
            $table->unsignedInteger('usuario_staff_id')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->foreign('usuario_staff_id')->references('usuario_id')->on('users')->nullOnDelete();
        });

        Schema::table('fin_egresos', function (Blueprint $table) {
            $table->foreignId('plantilla_id')->nullable()->after('categoria_id')->constrained('fin_egreso_plantillas')->nullOnDelete();
            $table->unsignedInteger('usuario_staff_id')->nullable()->after('usuario_id');
            $table->string('documento_path', 255)->nullable();
            $table->string('factura_path', 255)->nullable();
            $table->foreign('usuario_staff_id')->references('usuario_id')->on('users')->nullOnDelete();
        });

        $staff = DB::table('users')->pluck('usuario_id', 'name');
        $ahora = now();
        $filas = [
            ['Antena', 300000, false, null],
            ['Tigo N2', 15187000, false, null],
            ['Ufinet', 11584366, false, null],
            ['Starlink x4', 1392000, false, null],
            ['Sueldo JL', 4000000, true, $staff['Jose Luis Martinez'] ?? null],
            ['Hugo cancelacion deuda 7/10', 5000000, false, null],
            ['Pago Nino ganancia', 2000000, false, null],
            ['Pago Eladio / alquiler local', 1000000, false, null],
            ['Publicidad Candil', 200000, false, null],
            ['Sueldo Hugo', 3044000, true, $staff['Hugo Vera'] ?? null],
            ['Sueldo Sergio', 3500000, true, $staff['SERGIO DIAZ'] ?? null],
            ['Estudio contable', 700000, false, null],
            ['Sueldo Marina', 3044000, true, $staff['MARINA CABRERA'] ?? null],
        ];
        foreach ($filas as $i => [$nombre, $monto, $sueldo, $usuario]) {
            DB::table('fin_egreso_plantillas')->insert([
                'nombre' => $nombre,
                'monto' => $monto,
                'es_sueldo' => $sueldo,
                'usuario_staff_id' => $usuario,
                'orden' => $i + 1,
                'activa' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('fin_egresos', function (Blueprint $table) {
            $table->dropForeign(['plantilla_id']);
            $table->dropForeign(['usuario_staff_id']);
            $table->dropColumn(['plantilla_id', 'usuario_staff_id', 'documento_path', 'factura_path']);
        });
        Schema::dropIfExists('fin_egreso_plantillas');
    }
};
