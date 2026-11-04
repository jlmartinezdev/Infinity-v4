<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PushAviso extends Model
{
    protected $table = 'push_avisos';

    protected $fillable = [
        'titulo',
        'cuerpo',
        'tipo',
        'destino',
        'dias',
        'cliente_ids',
        'total_destinatarios',
        'enviados',
        'fallidos',
        'omitidos',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'cliente_ids' => 'array',
            'dias' => 'integer',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por', 'usuario_id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(PushAvisoEntrega::class, 'push_aviso_id');
    }

    public static function tipos(): array
    {
        return [
            'aviso' => 'Aviso',
            'promocion' => 'Promoción',
        ];
    }

    /**
     * Destinos del panel. Los de grupo se recalculan al enviar y al reenviar.
     *
     * @return array<string, string>
     */
    public static function destinos(): array
    {
        return [
            'seleccionados' => 'Clientes seleccionados',
            'todos' => 'Todos con app',
            'pendiente_pago' => 'Con saldo pendiente',
            'vencidas' => 'Con factura vencida',
            'por_vencer' => 'Por vencer',
            'suspendidos' => 'Servicio suspendido',
            'cortados' => 'Servicio cortado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function descripcionesDestino(): array
    {
        return [
            'seleccionados' => 'Elegís uno o más clientes que tengan la app.',
            'todos' => 'Todos los clientes con la app y push activo.',
            'pendiente_pago' => 'Quienes tienen facturas pendientes o emitidas con saldo.',
            'vencidas' => 'Quienes tienen al menos una factura impaga ya vencida.',
            'por_vencer' => 'Quienes tienen una factura impaga que vence hoy o dentro de los días indicados. No incluye las que ya vencieron.',
            'suspendidos' => 'Quienes tienen al menos un servicio suspendido.',
            'cortados' => 'Quienes tienen al menos un servicio cortado.',
        ];
    }

    public function etiquetaDestino(): string
    {
        if ($this->destino === 'todos') {
            return 'Todos';
        }

        if ($this->destino !== 'seleccionados') {
            $label = self::destinos()[$this->destino] ?? (string) $this->destino;
            if ($this->destino === 'por_vencer') {
                $n = max(1, (int) ($this->dias ?? 1));
                $label .= ' ('.$n.' '.($n === 1 ? 'día' : 'días').')';
            }

            return $label;
        }

        $ids = collect($this->cliente_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return 'Seleccionados (0)';
        }

        if ($ids->count() <= 3) {
            return $ids->map(fn ($id) => 'Cliente #'.$id)->implode(', ');
        }

        return 'Cliente #'.$ids->first().' y '.($ids->count() - 1).' más';
    }
}
