<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioHotspot extends Model
{
    use Auditable;

    public const MAX_POR_CLIENTE = 3;

    public const PIN_DIGITOS = 4;

    public const RECIENTES_MAX = 8;

    public const SESSION_RECIENTES = 'hotspot_recientes';

    protected $table = 'servicio_hotspot';

    protected $fillable = [
        'cliente_id',
        'slot_numero',
        'servicio_id',
        'router_id',
        'hotspot_perfil_id',
        'username',
        'password',
        'comment',
        'ros_id',
        'last_synced',
    ];

    protected function casts(): array
    {
        return [
            'last_synced' => 'datetime',
            'slot_numero' => 'integer',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'servicio_id');
    }

    /**
     * @return list<int>
     */
    public static function slotsDisponibles(): array
    {
        return range(1, self::MAX_POR_CLIENTE);
    }

    /**
     * @param  list<int>  $ocupados
     * @return list<int>
     */
    public static function slotsLibresDe(array $ocupados): array
    {
        return array_values(array_diff(self::slotsDisponibles(), array_map('intval', $ocupados)));
    }

    public static function usernameDesdeDocumento(Cliente $cliente, int $slot): ?string
    {
        $base = Cliente::cedulaSinSeparadores($cliente->cedula);
        if ($base === '') {
            return null;
        }

        return $slot <= 1 ? $base : $base.'-'.$slot;
    }

    public static function generarPassword(): string
    {
        $min = 10 ** (self::PIN_DIGITOS - 1);
        $max = (10 ** self::PIN_DIGITOS) - 1;

        return (string) random_int($min, $max);
    }

    public static function pinOculto(?string $password = null): string
    {
        $n = ($password !== null && $password !== '')
            ? mb_strlen($password)
            : self::PIN_DIGITOS;

        return str_repeat('•', max($n, 1));
    }

    /**
     * @return list<string>
     */
    public static function reglasPin(bool $requerido = false): array
    {
        return [
            $requerido ? 'required' : 'nullable',
            'digits:'.self::PIN_DIGITOS,
        ];
    }

    public static function mensajePin(): string
    {
        return 'El PIN tiene que tener '.self::PIN_DIGITOS.' números.';
    }

    public static function recordarCliente(int $clienteId): void
    {
        if ($clienteId <= 0) {
            return;
        }

        $ids = collect(session(self::SESSION_RECIENTES, []))
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id <= 0 || $id === $clienteId)
            ->prepend($clienteId)
            ->unique()
            ->take(self::RECIENTES_MAX)
            ->values()
            ->all();

        session([self::SESSION_RECIENTES => $ids]);
    }

    /**
     * @return list<int>
     */
    public static function idsRecientes(): array
    {
        $session = collect(session(self::SESSION_RECIENTES, []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $ids = $session->take(self::RECIENTES_MAX);
        $faltan = self::RECIENTES_MAX - $ids->count();
        if ($faltan <= 0) {
            return $ids->all();
        }

        $fallback = self::query()
            ->select('cliente_id')
            ->selectRaw('MAX(COALESCE(last_synced, updated_at)) as tocado')
            ->whereNotNull('cliente_id')
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereNotIn('cliente_id', $ids->all()))
            ->groupBy('cliente_id')
            ->orderByDesc('tocado')
            ->limit($faltan)
            ->pluck('cliente_id')
            ->map(fn ($id) => (int) $id);

        return $ids->concat($fallback)->all();
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_id', 'router_id');
    }

    public function hotspotPerfil(): BelongsTo
    {
        return $this->belongsTo(HotspotPerfil::class, 'hotspot_perfil_id', 'hotspot_perfil_id');
    }
}
