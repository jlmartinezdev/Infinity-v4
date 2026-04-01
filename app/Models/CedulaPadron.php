<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CedulaPadron extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'cedula';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cedula';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'NRODOC';

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
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'TIPODOC',
        'NRODOC',
        'NOMBRE',
        'APELLIDO',
        'FECHANAC',
        'DIREC',
        'SEXO',
        'DOMIC',
        'DAT1',
        'DAT2',
        'NROCI',
    ];

    /**
     * Buscar por número de documento.
     */
    public static function buscarPorCedula($numero)
    {
        return static::where('NRODOC', $numero)->first();
    }
}
