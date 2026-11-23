<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SorteoCampana extends Model
{
    protected $table = 'sorteo_campanas';

    protected $fillable = [
        'nombre',
        'texto_ticket',
        'texto_invitacion',
        'fecha_sorteo',
        'timezone',
        'periodo_uso_desde',
        'periodo_uso_hasta',
        'pago_limite',
        'criterio_puntualidad',
        'dia_puntualidad',
        'meses_puntualidad_100',
        'exige_app',
        'permite_multiples_premios',
        'activa',
        'imprimir_en_recibo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_sorteo' => 'date',
            'periodo_uso_desde' => 'date',
            'periodo_uso_hasta' => 'date',
            'pago_limite' => 'datetime',
            'meses_puntualidad_100' => 'array',
            'exige_app' => 'boolean',
            'permite_multiples_premios' => 'boolean',
            'activa' => 'boolean',
            'imprimir_en_recibo' => 'boolean',
            'dia_puntualidad' => 'integer',
        ];
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(SorteoPremioCategoria::class, 'campana_id')->orderBy('orden');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SorteoTicket::class, 'campana_id');
    }

    public static function activa(): ?self
    {
        return static::query()->where('activa', true)->orderByDesc('id')->first();
    }

    public function limite(): Carbon
    {
        $texto = $this->pago_limite instanceof Carbon
            ? $this->pago_limite->format('Y-m-d H:i:s')
            : (string) $this->pago_limite;

        return Carbon::createFromFormat('Y-m-d H:i:s', $texto, $this->timezone ?: 'America/Asuncion');
    }

    public function activar(): void
    {
        static::query()->where('id', '!=', $this->id)->update(['activa' => false]);
        $this->update(['activa' => true]);
    }
}
