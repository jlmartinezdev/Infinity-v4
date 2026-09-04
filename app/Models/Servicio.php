<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Servicio extends Model
{
    use Auditable;

    protected $table = 'servicios';

    protected $primaryKey = 'servicio_id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'cliente_id',
        'servicio_id',
        'pool_id',
        'plan_id',
        'alias',
        'pedido_id',
        'ip',
        'usuario_pppoe',
        'password_pppoe',
        'fecha_instalacion',
        'fecha_cancelacion',
        'estado',
        'fecha_suspension',
        'motivo_suspension',
        'mac_address',
        'tr069_serial',
        'tr069_product_class',
        'cpe_acceso',
        'cpe_onu',
        'cpe_router',
        'cpe_antena',
        'cpe_notas',
        'pppoe_status',
        'pppoe_synced',
        'estado_pago',
        'saldo_a_favor',
        'app_tv',
        'cantidad_perfil_app',
        'precio_app',
        'acuerdo_tipo',
        'acuerdo_meses',
        'acuerdo_desde',
    ];

    const ESTADO_ACTIVO = 'A';

    const ESTADO_SUSPENDIDO = 'S';

    const ESTADO_CORTADO = 'C';

    const ESTADO_CANCELADO = 'X';

    const ESTADO_PENDIENTE = 'P';

    public static function estadosDisponibles(): array
    {
        return [
            self::ESTADO_ACTIVO => 'Activo',
            self::ESTADO_SUSPENDIDO => 'Suspendido',
            self::ESTADO_CORTADO => 'Cortado',
            self::ESTADO_CANCELADO => 'Cancelado',
            self::ESTADO_PENDIENTE => 'Pendiente',
        ];
    }

    protected function casts(): array
    {
        return [
            'fecha_instalacion' => 'date',
            'fecha_cancelacion' => 'date',
            'fecha_suspension' => 'date',
            'pppoe_synced' => 'datetime',
            'saldo_a_favor' => 'decimal:2',
            'app_tv' => 'boolean',
            'cantidad_perfil_app' => 'integer',
            'precio_app' => 'decimal:2',
            'acuerdo_meses' => 'integer',
            'acuerdo_desde' => 'date',
        ];
    }

    public const ACUERDO_TIPO_NINGUNO = 'ninguno';

    public const ACUERDO_TIPO_LIBRE = 'libre';

    public const ACUERDO_TIPO_MESES = 'meses';

    public static function acuerdosDisponibles(): array
    {
        return [
            self::ACUERDO_TIPO_NINGUNO => 'Sin acuerdo',
            self::ACUERDO_TIPO_LIBRE => 'Internet libre (sin facturar)',
            self::ACUERDO_TIPO_MESES => 'Meses sin facturar',
        ];
    }

    public function aliasNormalizado(): ?string
    {
        $alias = trim((string) ($this->alias ?? ''));

        return $alias !== '' ? $alias : null;
    }

    public static function normalizarFragmentoUsuarioPppoe(?string $texto): string
    {
        $texto = str_replace(['ñ', 'Ñ'], 'n', trim((string) $texto));
        $texto = str_replace(' ', '_', Str::upper(Str::ascii($texto)));

        return (string) preg_replace('/[^A-Z0-9._-]/', '', $texto);
    }

    public static function baseUsuarioPppoeDesdeCliente(?Cliente $cliente = null, ?int $clienteId = null): string
    {
        $nombre = self::normalizarFragmentoUsuarioPppoe($cliente->nombre ?? '');
        $apellido = self::normalizarFragmentoUsuarioPppoe($cliente->apellido ?? '');
        $base = trim($nombre.($nombre !== '' && $apellido !== '' ? '_' : '').$apellido, '_');
        if (strlen($base) < 2) {
            return 'CLIENTE'.(int) ($cliente?->cliente_id ?? $clienteId ?? 0);
        }

        return $base;
    }

    public static function componerUsuarioPppoe(string $base, ?string $alias = null): string
    {
        $base = self::normalizarFragmentoUsuarioPppoe($base);
        $aliasNorm = self::normalizarFragmentoUsuarioPppoe($alias);
        if (strlen($base) < 2) {
            $base = 'CLIENTE';
        }
        if ($aliasNorm === '' || $base === $aliasNorm || str_ends_with($base, '_'.$aliasNorm)) {
            return $base;
        }

        return $base.'_'.$aliasNorm;
    }

    /**
     * @param  list<string>  $ocupados
     */
    public static function siguienteUsuarioPppoeLibre(string $base, array $ocupados): string
    {
        $base = self::normalizarFragmentoUsuarioPppoe($base);
        if (strlen($base) < 2) {
            $base = 'CLIENTE';
        }
        $tomados = [];
        foreach ($ocupados as $ocupado) {
            $norm = trim((string) $ocupado);
            if ($norm !== '') {
                $tomados[$norm] = true;
            }
        }
        $usuario = $base;
        $n = 1;
        while (isset($tomados[$usuario])) {
            $n++;
            $usuario = $base.'_'.$n;
        }

        return $usuario;
    }

    public static function usuarioPppoeDisponible(string $base, ?int $exceptoServicioId = null): string
    {
        $query = self::query()->whereNotNull('usuario_pppoe')->where('usuario_pppoe', '!=', '');
        if ($exceptoServicioId) {
            $query->where('servicio_id', '!=', $exceptoServicioId);
        }

        return self::siguienteUsuarioPppoeLibre($base, $query->pluck('usuario_pppoe')->all());
    }

    public static function usuarioPppoeDesdeClienteYAlias(?Cliente $cliente, ?string $alias = null, ?int $exceptoServicioId = null, ?int $clienteId = null): string
    {
        $base = self::componerUsuarioPppoe(
            self::baseUsuarioPppoeDesdeCliente($cliente, $clienteId ?? $cliente?->cliente_id),
            $alias
        );

        return self::usuarioPppoeDisponible($base, $exceptoServicioId);
    }

    /**
     * @param  list<string>  $evitar
     */
    public static function generarPasswordPppoe(array $evitar = [], int $largo = 8): string
    {
        $evitar = array_values(array_filter(array_map('strval', $evitar), fn (string $p) => $p !== ''));
        for ($i = 0; $i < 24; $i++) {
            $password = Str::random($largo);
            if (! in_array($password, $evitar, true)) {
                return $password;
            }
        }

        return Str::random($largo);
    }

    /**
     * Texto para distinguir el servicio cuando el cliente tiene más de uno (alias + plan).
     */
    public function etiqueta(): string
    {
        $alias = $this->aliasNormalizado();
        $plan = trim((string) ($this->plan?->nombre ?? ''));
        if ($alias && $plan !== '') {
            return $alias.' · '.$plan;
        }
        if ($alias) {
            return $alias;
        }

        return $plan !== '' ? $plan : 'Servicio #'.$this->servicio_id;
    }

    public function acuerdoAplicaEnPeriodo(Carbon $periodoDesde, Carbon $periodoHasta): bool
    {
        $tipo = (string) ($this->acuerdo_tipo ?: self::ACUERDO_TIPO_NINGUNO);
        if ($tipo === self::ACUERDO_TIPO_LIBRE) {
            return true;
        }
        if ($tipo !== self::ACUERDO_TIPO_MESES) {
            return false;
        }

        $meses = (int) ($this->acuerdo_meses ?? 0);
        if ($meses <= 0) {
            return false;
        }

        $desde = $this->acuerdo_desde
            ? Carbon::parse($this->acuerdo_desde)->startOfMonth()
            : ($this->fecha_instalacion ? Carbon::parse($this->fecha_instalacion)->startOfMonth() : null);
        if (! $desde) {
            return false;
        }

        $hasta = $desde->copy()->addMonthsNoOverflow($meses - 1)->endOfMonth();
        $periodoDesde = $periodoDesde->copy()->startOfDay();
        $periodoHasta = $periodoHasta->copy()->endOfDay();

        return $desde->lte($periodoHasta) && $hasta->gte($periodoDesde);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'plan_id');
    }

    public function tvCuentaAsignaciones(): HasMany
    {
        return $this->hasMany(TvCuentaAsignacion::class, 'servicio_id', 'servicio_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'pedido_id');
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(RouterIpPool::class, 'pool_id', 'pool_id');
    }

    public function servicioHotspot(): HasOne
    {
        return $this->hasOne(ServicioHotspot::class, 'servicio_id', 'servicio_id');
    }

    /** Puerto FTTH en caja NAP (si el servicio está empalado en fibra). */
    public function cajaNapPuertoActivo(): HasOne
    {
        return $this->hasOne(CajaNapPuertoActivo::class, 'servicio_id', 'servicio_id');
    }

    /**
     * Servicios cuyo router (pool) o caja NAP pertenece al nodo indicado.
     */
    public function scopeEnNodo($query, int $nodoId)
    {
        return $query->where(function ($q) use ($nodoId) {
            $q->whereHas('pool.router', fn ($r) => $r->where('nodo_id', $nodoId))
                ->orWhereHas('cajaNapPuertoActivo.cajaNap', fn ($c) => $c->where('nodo_id', $nodoId));
        });
    }

    public function conexionEventos(): HasMany
    {
        return $this->hasMany(ServicioConexionEvento::class, 'servicio_id', 'servicio_id');
    }

    /**
     * Servicio cargado a mano (p. ej. segundo enlace) y aún no cerrado como instalación este mes.
     * Los que vienen de un pedido se finalizan desde pedidos.
     */
    public function esCandidatoFinalizarInstalacion(?Carbon $hoy = null): bool
    {
        if ($this->estado === self::ESTADO_CANCELADO) {
            return false;
        }
        if (! empty($this->pedido_id)) {
            return false;
        }

        $hoy = $hoy ? $hoy->copy()->startOfDay() : Carbon::now()->startOfDay();
        if (! $this->fecha_instalacion) {
            return true;
        }

        $fecha = Carbon::parse($this->fecha_instalacion)->startOfDay();

        return $fecha->year === $hoy->year && $fecha->month === $hoy->month;
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function estaSuspendido(): bool
    {
        return $this->estado === self::ESTADO_SUSPENDIDO;
    }

    /**
     * Suspender servicio (por falta de pago u otro motivo).
     */
    public function suspender(string $motivo = 'Falta de pago'): void
    {
        $this->update([
            'estado' => self::ESTADO_SUSPENDIDO,
            'fecha_suspension' => now()->toDateString(),
            'motivo_suspension' => $motivo,
        ]);
    }

    /**
     * Reactivar servicio (pago recibido o manual).
     */
    public function activar(): void
    {
        $this->update([
            'estado' => self::ESTADO_ACTIVO,
            'fecha_suspension' => null,
            'motivo_suspension' => null,
        ]);
    }
}
