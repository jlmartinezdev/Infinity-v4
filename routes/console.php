<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('crear-factura-internas', function () {
    return $this->call('facturas:crear-internas-automaticas', [
        '--force' => true,
    ]);
})->purpose('Alias: crea facturas internas automáticas para clientes con servicios activos (mes actual, --force).');
