<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cliente extends Model
{
    use Auditable;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'cliente_id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    public function getRouteKeyName(): string
    {
        return 'cliente_id';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cedula',
        'ruc_consultado',
        'nombre',
        'apellido',
        'email',
        'telefono',
        'direccion',
        'url_ubicacion',
        'estado',
        'calificacion_pago',
        'ultimo_ingreso',
        'dispositivo',
        'app_version',
        'app_activa',
        'fecha_activacion_app',
        'fecha_otorgamiento',
        'aprobado_por',
        'referido_codigo',
        'referido_por_cliente_id',
    ];

    public const CALIFICACION_MALO = 'malo';
    public const CALIFICACION_BUENO = 'bueno';
    public const CALIFICACION_EXCELENTE = 'excelente';

    public static function calificacionesPago(): array
    {
        return [
            self::CALIFICACION_MALO => 'Malo',
            self::CALIFICACION_BUENO => 'Bueno',
            self::CALIFICACION_EXCELENTE => 'Excelente',
        ];
    }

    public function getCalificacionPagoLabelAttribute(): ?string
    {
        return $this->calificacion_pago
            ? (self::calificacionesPago()[$this->calificacion_pago] ?? $this->calificacion_pago)
            : null;
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ruc_consultado' => 'boolean',
            'ultimo_ingreso' => 'datetime',
            'app_activa' => 'boolean',
            'fecha_activacion_app' => 'datetime',
            'fecha_otorgamiento' => 'datetime',
        ];
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'cliente_id', 'cliente_id');
    }

    public static function cedulaSinSeparadores(?string $cedula): string
    {
        return str_replace(['.', '-', ' ', '/'], '', trim((string) $cedula));
    }

    /**
     * Localiza cliente por cédula exacta o sin separadores. Si hay duplicados, prefiere el que ya tiene servicio.
     */
    public static function buscarPorCedula(?string $cedula): ?self
    {
        $raw = trim((string) $cedula);
        if ($raw === '') {
            return null;
        }

        $sinSep = self::cedulaSinSeparadores($raw);
        $digits = preg_replace('/\D+/', '', $sinSep) ?? '';
        $needle = (preg_match('/[A-Za-z]/', $sinSep) || strlen($digits) < 5) ? $sinSep : $digits;
        if ($needle === '') {
            $needle = $raw;
        }

        $query = self::query()->where(function ($q) use ($raw, $needle) {
            $q->where('cedula', $raw);
            if ($needle !== $raw) {
                $q->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(cedula, '.', ''), '-', ''), ' ', ''), '/', '') = ?",
                    [$needle]
                );
            }
        });

        return $query
            ->withCount([
                'servicios as servicios_vigentes_count' => fn ($q) => $q->where('estado', '!=', Servicio::ESTADO_CANCELADO),
            ])
            ->orderByDesc('servicios_vigentes_count')
            ->orderByRaw("CASE estado WHEN 'activo' THEN 0 WHEN 'suspendido' THEN 1 WHEN 'solo_pedido' THEN 2 ELSE 3 END")
            ->first();
    }

    public function tieneServiciosVigentes(): bool
    {
        if ($this->relationLoaded('servicios')) {
            return $this->servicios->contains(fn (Servicio $s) => $s->estado !== Servicio::ESTADO_CANCELADO);
        }

        return $this->servicios()->where('estado', '!=', Servicio::ESTADO_CANCELADO)->exists();
    }

    /**
     * Reutiliza el cliente existente (p. ej. ya con servicio) o crea uno solo_pedido.
     *
     * @param  array{cedula: string, nombre?: string, apellido?: ?string, telefono?: ?string}  $datos
     */
    public static function resolverParaPedido(array $datos): self
    {
        $cedula = trim((string) ($datos['cedula'] ?? ''));
        $cliente = self::buscarPorCedula($cedula);
        if (! $cliente) {
            return self::create([
                'cedula' => $cedula,
                'nombre' => $datos['nombre'] ?? '',
                'apellido' => $datos['apellido'] ?? null,
                'telefono' => $datos['telefono'] ?? null,
                'estado' => 'solo_pedido',
            ]);
        }

        $updates = [];
        $yaTieneServicio = $cliente->tieneServiciosVigentes();
        if (! $yaTieneServicio) {
            if (! empty($datos['nombre'])) {
                $updates['nombre'] = $datos['nombre'];
            }
            if (! empty($datos['apellido'])) {
                $updates['apellido'] = $datos['apellido'];
            }
            if (! empty($datos['telefono'])) {
                $updates['telefono'] = $datos['telefono'];
            }
        } elseif (empty($cliente->telefono) && ! empty($datos['telefono'])) {
            $updates['telefono'] = $datos['telefono'];
        }

        if ($updates !== []) {
            $cliente->update($updates);
        }

        return $cliente;
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadParaPedido(): array
    {
        $this->loadMissing('servicios.plan');
        $vigentes = $this->servicios
            ->filter(fn (Servicio $s) => $s->estado !== Servicio::ESTADO_CANCELADO)
            ->values();

        return [
            'cliente_id' => $this->cliente_id,
            'cedula' => $this->cedula,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'direccion' => $this->direccion,
            'url_ubicacion' => $this->url_ubicacion,
            'estado' => $this->estado,
            'tiene_servicios' => $vigentes->isNotEmpty(),
            'servicios_count' => $vigentes->count(),
            'servicios' => $vigentes->map(fn (Servicio $s) => [
                'servicio_id' => $s->servicio_id,
                'alias' => $s->aliasNormalizado(),
                'etiqueta' => $s->etiqueta(),
            ])->all(),
        ];
    }

    public function cobros(): HasMany
    {
        return $this->hasMany(Cobro::class, 'cliente_id', 'cliente_id');
    }

    public function facturaInternas(): HasMany
    {
        return $this->hasMany(FacturaInterna::class, 'cliente_id', 'cliente_id');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'cliente_id', 'cliente_id');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class, 'cliente_id', 'cliente_id');
    }

    public function agendas(): HasMany
    {
        return $this->hasMany(Agenda::class, 'cliente_id', 'cliente_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'cliente_id', 'cliente_id');
    }

    public function usuarioPortal(): HasOne
    {
        return $this->hasOne(User::class, 'cliente_id', 'cliente_id');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por', 'usuario_id');
    }
}
