<?php

namespace App\Services\Sifen;

use Illuminate\Support\Facades\Artisan;

/**
 * Despacha jobs SIFEN a la cola y, tras responder al navegador,
 * arranca un worker corto (--stop-when-empty) para no depender
 * de un `queue:work` permanente (útil en XAMPP/Windows).
 */
class SifenBackground
{
    private static bool $workerProgramado = false;

    public static function dispatch(object $job): void
    {
        dispatch($job);

        if (self::$workerProgramado) {
            return;
        }

        self::$workerProgramado = true;

        dispatch(function () {
            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => 600,
                '--sleep' => 0,
                '--tries' => 1,
                '--quiet' => true,
            ]);
        })->afterResponse();
    }
}
