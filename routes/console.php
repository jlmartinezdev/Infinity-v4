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
})->purpose('Alias: crea facturas internas automáticas para clientes activos con servicio asociado; factura A/S/C (mes actual, --force).');

Artisan::command('verificar-facturas-mes-pasado', function () {
    return $this->call('facturas:verificar-mes-pasado-clientes-activos');
})->purpose('Alias: verifica si todos los clientes activos con servicio tienen factura interna del mes pasado.');

Artisan::command('verificar-servicios-suspendidos-mikrotik', function () {
    return $this->call('mikrotik:verificar-servicios-suspendidos');
})->purpose('Alias: verifica que servicios suspendidos en BD tambien esten suspendidos en MikroTik.');

Artisan::command('auditar-cobros-pivote', function () {
    return $this->call('cobros:auditar-pivote');
})->purpose('Alias: audita diferencias entre cobros y tabla pivote cobro_factura_interna.');

Artisan::command('auditar-dashboard-inicio-cobros', function () {
    return $this->call('cobros:auditar-dashboard-inicio');
})->purpose('Alias: compara cobros.monto vs pivote como en dashboard de Inicio.');
