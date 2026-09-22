<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionDolar extends Model
{
    protected $table = 'cotizaciones_dolar';

    protected $fillable = [
        'valor',
        'fecha',
        'notas',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    public static function actual(): ?self
    {
        return static::query()->latest('id')->first();
    }

    public static function valorActualInput(): string
    {
        $actual = static::actual();

        return $actual ? Producto::valorInput($actual->valor) : '';
    }

    public static function etiquetaDe(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return '1 USD = '.Producto::formatoNumero($valor).' Gs';
    }

    public function etiqueta(): string
    {
        return self::etiquetaDe($this->valor);
    }

    /** @return array<int, string> */
    public static function reglasTipoCambio(): array
    {
        return ['required', 'numeric', 'min:1', 'max:999999'];
    }
}
