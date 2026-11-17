<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use Auditable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'planes';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'plan_id';

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

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tecnologia_id',
        'perfil_pppoe_id',
        'nombre',
        'velocidad',
        'precio', // Mensual con IVA incluido (PYG)
        'descripcion',
        'estado',
        'prioridad',
    ];

    /**
     * Usuarios hotspot que da cada nivel de plan. Un plan sin nivel en el nombre cuenta como básico.
     */
    public const SLOTS_HOTSPOT = [
        'basico' => 1,
        'estandar' => 2,
        'premium' => 3,
    ];

    public const ETIQUETAS_NIVEL = [
        'basico' => 'Básico',
        'estandar' => 'Estándar',
        'premium' => 'Premium',
    ];

    /**
     * Nivel según el nombre del plan, con la misma regla que el filtro de Pedidos.
     *
     * @return 'premium'|'estandar'|'basico'|''
     */
    public static function nivelDesdeNombre(?string $nombre): string
    {
        $n = mb_strtolower(\Illuminate\Support\Str::ascii((string) $nombre));
        if (str_contains($n, 'premium')) {
            return 'premium';
        }
        if (str_contains($n, 'estandar')) {
            return 'estandar';
        }
        if (str_contains($n, 'basico')) {
            return 'basico';
        }

        return '';
    }

    public static function slotsHotspotDeNivel(string $nivel): int
    {
        return self::SLOTS_HOTSPOT[$nivel] ?? self::SLOTS_HOTSPOT['basico'];
    }

    /**
     * Nivel mínimo de plan que habilita ese slot (1 → básico, 2 → estándar, 3 → premium).
     */
    public static function nivelParaSlot(int $slot): string
    {
        foreach (self::SLOTS_HOTSPOT as $nivel => $slots) {
            if ($slot <= $slots) {
                return $nivel;
            }
        }

        return 'premium';
    }

    public function nivel(): string
    {
        return self::nivelDesdeNombre($this->nombre);
    }

    /**
     * Perfil PPPoE asociado al plan (opcional).
     */
    public function perfilPppoe(): BelongsTo
    {
        return $this->belongsTo(PerfilPppoe::class, 'perfil_pppoe_id', 'perfil_pppoe_id');
    }

    public function tipoTecnologia(): BelongsTo
    {
        return $this->belongsTo(TipoTecnologia::class, 'tecnologia_id', 'tecnologia_id');
    }

    /**
     * Servicios asociados al plan.
     */
    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'plan_id', 'plan_id');
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'plan_id';
    }

    /**
     * Retrieve the model for route model binding.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();
        return $this->where($field, $value)->first();
    }

    /**
     * Retrieve the child model for a bound value.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveChildRouteBinding($childType, $value, $field)
    {
        return $this->resolveRouteBinding($value, $field);
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
        ];
    }

    /**
     * Plan de destino con el precio más cercano al origen (empate: más barato, luego plan_id).
     *
     * @param  iterable<int, self>  $planesDestino
     */
    public static function equivalentePorPrecio(float $precioOrigen, iterable $planesDestino): ?self
    {
        $planes = collect($planesDestino)->filter(fn ($p) => $p instanceof self);
        if ($planes->isEmpty()) {
            return null;
        }

        return $planes->sortBy(fn (self $plan) => [
            abs((float) $plan->precio - $precioOrigen),
            (float) $plan->precio,
            (int) ($plan->plan_id ?? 0),
        ])->first();
    }

    /** Abreviatura del plan para listados (ej. «Fibra 50» → «F5» o «50MB» → «50MB»). */
    public function iniciales(): string
    {
        $nombre = trim((string) ($this->nombre ?? ''));
        if ($nombre === '') {
            $vel = trim((string) ($this->velocidad ?? ''));

            return $vel !== '' ? mb_strtoupper($vel) : '—';
        }

        $partes = preg_split('/[\s\-\/]+/u', $nombre, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($partes) === 1) {
            $unico = $partes[0];
            if (mb_strlen($unico) <= 5) {
                return mb_strtoupper($unico);
            }

            return mb_strtoupper(mb_substr($unico, 0, 3));
        }

        $ini = '';
        foreach ($partes as $parte) {
            if (preg_match('/^\d/u', $parte)) {
                $ini .= preg_replace('/[^0-9]/', '', $parte) ?: mb_substr($parte, 0, 1);
            } else {
                $ini .= mb_strtoupper(mb_substr($parte, 0, 1));
            }
        }

        return mb_substr($ini, 0, 6) ?: '—';
    }
}
