<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushAviso extends Model
{
    protected $table = 'push_avisos';

    protected $fillable = [
        'titulo',
        'cuerpo',
        'tipo',
        'destino',
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
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por', 'usuario_id');
    }

    public static function tipos(): array
    {
        return [
            'aviso' => 'Aviso',
            'promocion' => 'Promoción',
        ];
    }

    public function etiquetaDestino(): string
    {
        if ($this->destino === 'todos') {
            return 'Todos';
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
