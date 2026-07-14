<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OltOnu extends Model
{
    protected $table = 'olt_onus';

    protected $primaryKey = 'olt_onu_id';

    protected $fillable = [
        'olt_id',
        'pon_slot',
        'pon_port',
        'pon_key',
        'onu_index',
        'serial',
        'vendor_id',
        'modelo',
        'descripcion',
        'estado',
        'rx_power_dbm',
        'tx_power_dbm',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'pon_slot' => 'integer',
            'pon_port' => 'integer',
            'onu_index' => 'integer',
            'rx_power_dbm' => 'decimal:2',
            'tx_power_dbm' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class, 'olt_id', 'olt_id');
    }

    public function estadoEtiqueta(): string
    {
        $map = [
            'working' => 'Online',
            'online' => 'Online',
            'up' => 'Online',
            'active' => 'Online',
            'offline' => 'Offline',
            'down' => 'Offline',
            'los' => 'LOS',
            'dyinggasp' => 'Dying gasp',
            'unknown' => 'Desconocido',
        ];

        return $map[strtolower($this->estado ?? '')] ?? ucfirst($this->estado ?? '—');
    }

    public function estadoEsOnline(): bool
    {
        return in_array(strtolower((string) $this->estado), ['working', 'online', 'up', 'active'], true);
    }

    public function estadoEsOffline(): bool
    {
        return in_array(strtolower((string) $this->estado), ['offline', 'down', 'deactive', 'deactivated', 'registering', 'los', 'dyinggasp', 'auth-fail', 'config-fail'], true);
    }

    public function etiquetaDescripcion(): string
    {
        return trim((string) ($this->descripcion ?: $this->modelo ?: '')) ?: '—';
    }

    public function rxEsOptimo(): ?bool
    {
        if ($this->rx_power_dbm === null) {
            return null;
        }

        $rx = (float) $this->rx_power_dbm;

        return $rx >= -27 && $rx <= -8;
    }

    /**
     * ONU registrada en el OLT (tiene serial o modelo), no un slot vacío.
     */
    public function scopeRegistradas($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($q2) {
                $q2->whereNotNull('serial')->where('serial', '!=', '');
            })->orWhere(function ($q2) {
                $q2->whereNotNull('modelo')->where('modelo', '!=', '');
            });
        });
    }

    public function estaRegistrada(): bool
    {
        return filled($this->serial) || filled($this->modelo);
    }
}
