<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StaffEvidencia extends Model
{
    public const TIPO_VISITA = 'visita';

    public const TIPO_PEDIDO = 'pedido_instalacion';

    protected $table = 'staff_evidencias';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'usuario_id',
        'path',
        'caption',
        'client_photo_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    public function urlPublica(): string
    {
        return url(Storage::disk('public')->url($this->path));
    }

    /**
     * @return array{id: string, url: string, entity_type: string, entity_id: string, caption: string|null, client_photo_id: string|null}
     */
    public function toApiArray(): array
    {
        return [
            'id' => 'srv-'.$this->id,
            'url' => $this->urlPublica(),
            'entity_type' => $this->entity_type,
            'entity_id' => (string) $this->entity_id,
            'caption' => $this->caption,
            'client_photo_id' => $this->client_photo_id,
        ];
    }
}
