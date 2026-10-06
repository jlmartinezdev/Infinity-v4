<?php

namespace App\Services\Portal;

use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\ServicioHotspot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Slots hotspot (máx. 3) gestionables desde la app del cliente.
 * La sync RADIUS corre vía ServicioHotspotObserver.
 */
class PortalHotspotSlotsService
{
    public function __construct(
        private readonly PortalFeatureFlagsService $flags,
    ) {}

    public function flagHabilitado(): bool
    {
        foreach ($this->flags->flags() as $flag) {
            if (($flag['key'] ?? '') === 'hotspot_slots') {
                return ($flag['state'] ?? '') === 'enabled';
            }
        }

        return false;
    }

    /**
     * @return array{
     *   max: int,
     *   ocupados: int,
     *   slots_libres: list<int>,
     *   slots: list<array<string, mixed>>,
     *   servicios: list<array{servicio_id: int, label: string}>
     * }
     */
    public function listar(Cliente $cliente): array
    {
        $this->assertFlag();

        $slots = $cliente->servicioHotspots()
            ->with(['servicio.plan', 'hotspotPerfil'])
            ->orderBy('slot_numero')
            ->get();

        $ocupados = $slots->pluck('slot_numero')->map(fn ($n) => (int) $n)->all();
        $libres = ServicioHotspot::slotsLibresDe($ocupados);

        return [
            'max' => ServicioHotspot::MAX_POR_CLIENTE,
            'ocupados' => count($ocupados),
            'slots_libres' => $libres,
            'slots' => $slots->map(fn (ServicioHotspot $s) => $this->slotPayload($s))->values()->all(),
            'servicios' => $this->serviciosDisponibles($cliente),
        ];
    }

    /**
     * @param  array{servicio_id?: int|null, slot_numero?: int|null, password?: string|null}  $input
     * @return array<string, mixed>
     */
    public function crear(Cliente $cliente, array $input): array
    {
        $this->assertFlag();

        if ($cliente->servicioHotspots()->count() >= ServicioHotspot::MAX_POR_CLIENTE) {
            throw ValidationException::withMessages([
                'slot_numero' => ['Ya tenés el máximo de '.ServicioHotspot::MAX_POR_CLIENTE.' usuarios hotspot.'],
            ]);
        }

        $ocupados = $cliente->servicioHotspots()->pluck('slot_numero')->map(fn ($n) => (int) $n)->all();
        $libres = ServicioHotspot::slotsLibresDe($ocupados);
        if ($libres === []) {
            throw ValidationException::withMessages([
                'slot_numero' => ['No hay slots libres.'],
            ]);
        }

        $slotNumero = isset($input['slot_numero']) ? (int) $input['slot_numero'] : (int) $libres[0];
        if (! in_array($slotNumero, $libres, true)) {
            throw ValidationException::withMessages([
                'slot_numero' => ['Ese slot ya está ocupado o no es válido (1–'.ServicioHotspot::MAX_POR_CLIENTE.').'],
            ]);
        }

        $servicioId = isset($input['servicio_id']) ? (int) $input['servicio_id'] : 0;
        $servicio = $this->resolverServicio($cliente, $servicioId > 0 ? $servicioId : null);

        $password = isset($input['password']) && $input['password'] !== null && $input['password'] !== ''
            ? (string) $input['password']
            : ServicioHotspot::generarPassword();

        if (! preg_match('/^\d{'.ServicioHotspot::PIN_DIGITOS.'}$/', $password)) {
            throw ValidationException::withMessages([
                'password' => [ServicioHotspot::mensajePin()],
            ]);
        }

        $username = ServicioHotspot::usernameDesdeDocumento($cliente, $slotNumero);
        if ($username === null) {
            throw ValidationException::withMessages([
                'username' => ['El cliente no tiene documento para generar el usuario hotspot.'],
            ]);
        }

        if (ServicioHotspot::where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'username' => ['Ya existe un usuario hotspot con ese documento.'],
            ]);
        }

        $row = DB::transaction(function () use ($cliente, $servicio, $slotNumero, $username, $password) {
            return ServicioHotspot::create([
                'cliente_id' => $cliente->cliente_id,
                'servicio_id' => $servicio->servicio_id,
                'slot_numero' => $slotNumero,
                'username' => $username,
                'password' => $password,
                'hotspot_perfil_id' => null,
                'router_id' => null,
                'comment' => trim($cliente->nombre.' '.$cliente->apellido),
            ]);
        });

        $row->load(['servicio.plan', 'hotspotPerfil']);

        return $this->slotPayload($row, true);
    }

    /**
     * @param  array{password?: string|null}  $input
     * @return array<string, mixed>
     */
    public function actualizarPassword(Cliente $cliente, ServicioHotspot $slot, array $input): array
    {
        $this->assertFlag();
        $this->assertOwnership($cliente, $slot);

        $password = isset($input['password']) ? (string) $input['password'] : '';
        if (! preg_match('/^\d{'.ServicioHotspot::PIN_DIGITOS.'}$/', $password)) {
            throw ValidationException::withMessages([
                'password' => [ServicioHotspot::mensajePin()],
            ]);
        }

        $slot->password = $password;
        $slot->save();
        $slot->load(['servicio.plan', 'hotspotPerfil']);

        return $this->slotPayload($slot, true);
    }

    public function eliminar(Cliente $cliente, ServicioHotspot $slot): void
    {
        $this->assertFlag();
        $this->assertOwnership($cliente, $slot);
        $slot->delete();
    }

    private function assertFlag(): void
    {
        if (! $this->flagHabilitado()) {
            throw ValidationException::withMessages([
                'hotspot' => ['La gestión de hotspot desde la app no está disponible.'],
            ]);
        }
    }

    private function assertOwnership(Cliente $cliente, ServicioHotspot $slot): void
    {
        if ((int) $slot->cliente_id !== (int) $cliente->cliente_id) {
            throw ValidationException::withMessages([
                'slot' => ['Slot no encontrado.'],
            ]);
        }
    }

    private function resolverServicio(Cliente $cliente, ?int $servicioId): Servicio
    {
        $q = Servicio::query()->where('cliente_id', $cliente->cliente_id);
        if ($servicioId) {
            $servicio = (clone $q)->where('servicio_id', $servicioId)->first();
            if (! $servicio) {
                throw ValidationException::withMessages([
                    'servicio_id' => ['El servicio no pertenece a tu cuenta.'],
                ]);
            }

            return $servicio;
        }

        $servicio = (clone $q)->orderBy('servicio_id')->first();
        if (! $servicio) {
            throw ValidationException::withMessages([
                'servicio_id' => ['No tenés un servicio activo para vincular el hotspot.'],
            ]);
        }

        return $servicio;
    }

    /**
     * @return list<array{servicio_id: int, label: string}>
     */
    private function serviciosDisponibles(Cliente $cliente): array
    {
        return $cliente->servicios()
            ->with('plan')
            ->orderBy('servicio_id')
            ->get()
            ->map(function (Servicio $s) {
                $plan = $s->plan?->nombre ?? $s->plan?->descripcion ?? 'Servicio';

                return [
                    'servicio_id' => (int) $s->servicio_id,
                    'label' => trim('#'.$s->servicio_id.' · '.$plan),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function slotPayload(ServicioHotspot $s, bool $incluirPassword = false): array
    {
        $payload = [
            'id' => (int) $s->getKey(),
            'slot_numero' => (int) $s->slot_numero,
            'username' => (string) $s->username,
            'password_masked' => ServicioHotspot::pinOculto($s->password),
            'servicio_id' => $s->servicio_id ? (int) $s->servicio_id : null,
            'servicio_label' => $s->servicio
                ? trim('#'.$s->servicio_id.' · '.($s->servicio->plan?->nombre ?? $s->servicio->plan?->descripcion ?? 'Servicio'))
                : null,
            'perfil' => $s->hotspotPerfil?->nombre,
            'last_synced' => optional($s->last_synced)?->toIso8601String(),
            'comment' => $s->comment,
        ];

        if ($incluirPassword) {
            $payload['password'] = (string) $s->password;
        }

        return $payload;
    }
}
