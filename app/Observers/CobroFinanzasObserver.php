<?php

namespace App\Observers;

use App\Models\Cobro;
use App\Services\Finanzas\FinanzasCobroPuente;

class CobroFinanzasObserver
{
    public function created(Cobro $cobro): void
    {
        app(FinanzasCobroPuente::class)->registrar($cobro);
    }

    public function deleting(Cobro $cobro): void
    {
        app(FinanzasCobroPuente::class)->revertirPorAnulacion($cobro);
    }
}
