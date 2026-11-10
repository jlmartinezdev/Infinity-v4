<?php

namespace Tests\Feature;

use App\Events\AccionChatAtendida;
use App\Events\MensajeChatCreado;
use App\Models\User;
use App\Services\FcmPushService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatInternoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('rol_id');
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('usuario_id');
            $table->unsignedInteger('rol_id')->nullable();
            $table->unsignedInteger('cliente_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('contrasena')->nullable();
            $table->text('permisos')->nullable();
            $table->string('estado')->default('activo');
            $table->string('cargo')->nullable();
            $table->string('push_token')->nullable();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['rol_id' => 1, 'descripcion' => 'Administrador', 'created_at' => now(), 'updated_at' => now()],
            ['rol_id' => 2, 'descripcion' => 'Tecnico', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->usuario(1, 'Ana', 2);
        $this->usuario(2, 'Beto', 2);
        $this->usuario(3, 'Carla', 2);
        $this->usuario(4, 'Admin', 1);
        $this->usuario(5, 'Portal', 2, 9, ['chat-interno.ver']);

        $migration = require base_path('database/migrations/2026_11_10_150000_create_chat_interno_tables.php');
        $migration->up();
    }

    public function test_envio_accion_archivo_y_acceso(): void
    {
        Storage::fake('local');
        Event::fake([MensajeChatCreado::class, AccionChatAtendida::class]);

        $avisos = [];
        $fcm = \Mockery::mock(FcmPushService::class);
        $fcm->shouldReceive('notifyUser')->andReturnUsing(function ($user, $title, $body, $data) use (&$avisos) {
            $avisos[] = [
                'usuario_id' => (int) $user->usuario_id,
                'title' => $title,
                'requiere_accion' => $data['requiere_accion'] ?? null,
                'type' => $data['type'] ?? null,
            ];

            return true;
        });
        $this->app->instance(FcmPushService::class, $fcm);

        $ana = User::query()->find(1);
        $this->assertContains('chat-interno.ver', $ana->permisos);
        $this->assertNotContains('chat-interno.ver', User::query()->find(4)->permisos ?? []);

        $directo = $this->actingAs($ana)->postJson('/chat/directos', ['usuario_id' => 2]);
        $directo->assertCreated();
        $conversacionId = $directo->json('conversacion.id');

        $this->actingAs($ana)->post('/chat/directos', ['usuario_id' => 2])->assertOk();

        $this->actingAs($ana)->post('/chat/conversaciones/'.$conversacionId.'/mensajes', [
            'cuerpo' => 'Revisar el enlace',
            'requiere_accion' => '1',
        ])->assertCreated();

        Event::assertDispatched(MensajeChatCreado::class, function (MensajeChatCreado $evento) {
            return ($evento->mensaje['cuerpo'] ?? null) === 'Revisar el enlace'
                && ($evento->mensaje['requiere_accion'] ?? false) === true
                && ! array_key_exists('ruta', $evento->mensaje)
                && ($evento->mensaje['adjunto'] === null || ! isset($evento->mensaje['adjunto']['ruta']));
        });

        $this->assertSame('chat_interno', $avisos[0]['type'] ?? null);
        $this->assertSame(2, $avisos[0]['usuario_id'] ?? null);
        $this->assertSame('1', $avisos[0]['requiere_accion'] ?? null);
        $this->assertSame('Acción requerida', $avisos[0]['title'] ?? null);

        $beto = User::query()->find(2);
        $pendientes = $this->actingAs($beto)->getJson('/chat/pendientes');
        $pendientes->assertOk();
        $pendientes->assertJsonPath('acciones.0.cuerpo', 'Revisar el enlace');
        $mensajeId = $pendientes->json('acciones.0.mensaje_id');

        $archivo = $this->actingAs($ana)->post('/chat/conversaciones/'.$conversacionId.'/mensajes', [
            'archivo' => UploadedFile::fake()->create('plano.pdf', 20, 'application/pdf'),
        ]);
        $archivo->assertCreated();
        $adjuntoId = $archivo->json('mensaje.adjunto.id');
        $this->assertNotNull($adjuntoId);
        $this->assertArrayNotHasKey('ruta', $archivo->json('mensaje.adjunto'));
        Storage::disk('local')->assertExists($this->rutaAdjunto());

        $this->actingAs($beto)->get('/chat/adjuntos/'.$adjuntoId)->assertOk();
        $this->actingAs(User::query()->find(3))->getJson('/chat/conversaciones/'.$conversacionId.'/mensajes')->assertForbidden();
        $this->actingAs(User::query()->find(3))->getJson('/chat/adjuntos/'.$adjuntoId)->assertForbidden();

        $this->actingAs($beto)->postJson('/chat/mensajes/'.$mensajeId.'/atender')->assertOk();
        Event::assertDispatched(AccionChatAtendida::class);
        $this->actingAs($beto)->getJson('/chat/pendientes')->assertJsonPath('acciones', []);

        $this->actingAs(User::query()->find(5), 'sanctum')
            ->getJson('/api/v1/staff/chat/conversaciones')
            ->assertForbidden();
    }

    public function test_grupo_sin_elegir_asigna_la_accion_al_resto(): void
    {
        Event::fake([MensajeChatCreado::class]);
        $this->app->instance(FcmPushService::class, \Mockery::mock(FcmPushService::class)->shouldIgnoreMissing());

        $ana = User::query()->find(1);
        $grupo = $this->actingAs($ana)->postJson('/chat/grupos', [
            'titulo' => 'Cuadrilla norte',
            'usuario_ids' => [2, 3],
        ]);
        $grupo->assertCreated();
        $id = $grupo->json('conversacion.id');

        $this->actingAs($ana)->post('/chat/conversaciones/'.$id.'/mensajes', [
            'cuerpo' => 'Hay que subir al nodo',
            'requiere_accion' => '1',
        ])->assertCreated();

        $this->actingAs(User::query()->find(2))->getJson('/chat/pendientes')
            ->assertJsonPath('acciones.0.cuerpo', 'Hay que subir al nodo');
        $this->actingAs(User::query()->find(3))->getJson('/chat/pendientes')
            ->assertJsonPath('acciones.0.cuerpo', 'Hay que subir al nodo');
    }

    private function usuario(int $id, string $nombre, int $rolId, ?int $clienteId = null, array $permisos = []): void
    {
        DB::table('users')->insert([
            'usuario_id' => $id,
            'rol_id' => $rolId,
            'cliente_id' => $clienteId,
            'name' => $nombre,
            'email' => strtolower($nombre).'@test.local',
            'contrasena' => 'secreto',
            'permisos' => json_encode($permisos),
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function rutaAdjunto(): string
    {
        $archivos = Storage::disk('local')->allFiles('chat');

        $this->assertNotEmpty($archivos);

        return $archivos[0];
    }
}
