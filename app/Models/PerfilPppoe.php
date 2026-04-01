<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerfilPppoe extends Model
{
    use Auditable;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'perfiles_pppoe';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'perfil_pppoe_id';

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
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'local_address',
        'remote_address',
        'rate_limit_tx_rx',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    /**
     * Planes que usan este perfil PPPoE.
     */
    public function planes(): HasMany
    {
        return $this->hasMany(Plan::class, 'perfil_pppoe_id', 'perfil_pppoe_id');
    }

    protected function casts(): array
    {
        return [];
    }
}
