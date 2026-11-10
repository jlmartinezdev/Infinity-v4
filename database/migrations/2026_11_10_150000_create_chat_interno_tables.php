<?php

use App\Models\Permiso;
use App\Models\Rol;
use App\Support\PermisosCatalogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);
            $table->string('titulo', 120)->nullable();
            $table->unsignedInteger('creado_por');
            $table->string('directo_clave', 40)->nullable();
            $table->timestamps();

            $table->unique('directo_clave');
            $table->index('tipo');
            $table->foreign('creado_por')->references('usuario_id')->on('users');
        });

        Schema::create('chat_participantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')->constrained('chat_conversaciones')->cascadeOnDelete();
            $table->unsignedInteger('usuario_id');
            $table->unsignedBigInteger('ultimo_leido_mensaje_id')->nullable();
            $table->timestamps();

            $table->unique(['conversacion_id', 'usuario_id']);
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->cascadeOnDelete();
        });

        Schema::create('chat_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')->constrained('chat_conversaciones')->cascadeOnDelete();
            $table->unsignedInteger('usuario_id');
            $table->text('cuerpo')->nullable();
            $table->timestamps();

            $table->index(['conversacion_id', 'id']);
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->cascadeOnDelete();
        });

        Schema::create('chat_mensaje_acciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensaje_id')->constrained('chat_mensajes')->cascadeOnDelete();
            $table->unsignedInteger('usuario_id');
            $table->timestamp('atendido_at')->nullable();
            $table->timestamps();

            $table->unique(['mensaje_id', 'usuario_id']);
            $table->index(['usuario_id', 'atendido_at']);
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->cascadeOnDelete();
        });

        Schema::create('chat_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensaje_id')->constrained('chat_mensajes')->cascadeOnDelete();
            $table->string('disco', 40)->default('local');
            $table->string('ruta');
            $table->string('nombre_original');
            $table->string('mime', 120);
            $table->unsignedInteger('tamano');
            $table->timestamps();

            $table->unique('mensaje_id');
        });

        $this->concederPermiso();
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_adjuntos');
        Schema::dropIfExists('chat_mensaje_acciones');
        Schema::dropIfExists('chat_mensajes');
        Schema::dropIfExists('chat_participantes');
        Schema::dropIfExists('chat_conversaciones');
    }

    private function concederPermiso(): void
    {
        $codigo = 'chat-interno.ver';

        if (Schema::hasTable('permisos')) {
            $fila = collect(PermisosCatalogo::filasParaSeeder())->firstWhere('codigo', $codigo);
            if (is_array($fila)) {
                Permiso::updateOrCreate(['codigo' => $codigo], $fila);
            }

            $permisoId = Permiso::query()->where('codigo', $codigo)->value('id');
            $admin = Rol::query()->whereRaw('LOWER(descripcion) = ?', ['administrador'])->first();
            if ($admin && $permisoId) {
                $admin->permisos()->syncWithoutDetaching([(int) $permisoId]);
            }
        }

        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'permisos')) {
            return;
        }

        $adminIds = Schema::hasTable('roles')
            ? DB::table('roles')->whereRaw('LOWER(descripcion) = ?', ['administrador'])->pluck('rol_id')
            : collect();

        $consulta = DB::table('users')->where('estado', 'activo');
        if (Schema::hasColumn('users', 'cliente_id')) {
            $consulta->whereNull('cliente_id');
        }
        if ($adminIds->isNotEmpty()) {
            $consulta->whereNotIn('rol_id', $adminIds->all());
        }

        foreach ($consulta->cursor() as $user) {
            $permisos = json_decode($user->permisos ?? '[]', true);
            if (! is_array($permisos)) {
                continue;
            }
            if (in_array($codigo, $permisos, true)) {
                continue;
            }
            $permisos[] = $codigo;
            DB::table('users')->where('usuario_id', $user->usuario_id)->update([
                'permisos' => json_encode(array_values($permisos)),
            ]);
        }
    }
};
