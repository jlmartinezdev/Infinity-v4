<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('importar-olt-onus', function () {
    $this->call('olt:import-onus', [
        '--all' => true,
    ]);
})->purpose('Importar ONUs de todos los OLTs VSOL con credenciales');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('crear-factura-internas', function () {
    return $this->call('facturas:crear-internas-automaticas', [
        '--force' => true,
    ]);
})->purpose('Alias: crea facturas internas automáticas para clientes activos con servicio asociado; factura A/S/C (mes actual, --force).');

Artisan::command('verificar-facturas-mes-pasado', function () {
    return $this->call('facturas:auditar-internas-mes-pasado', [
        '--solo-faltantes' => true,
    ]);
})->purpose('Alias: audita clientes activos sin factura interna del mes pasado (solo faltantes).');

Artisan::command('auditar-facturas-internas-mes-pasado', function () {
    return $this->call('facturas:auditar-internas-mes-pasado');
})->purpose('Alias: auditoría completa de facturas internas mensuales faltantes.');

Artisan::command('verificar-servicios-suspendidos-mikrotik', function () {
    return $this->call('mikrotik:verificar-servicios-suspendidos');
})->purpose('Alias: verifica que servicios suspendidos en BD tambien esten suspendidos en MikroTik.');

Artisan::command('auditar-cobros-pivote', function () {
    return $this->call('cobros:auditar-pivote');
})->purpose('Alias: audita diferencias entre cobros y tabla pivote cobro_factura_interna.');

Artisan::command('auditar-dashboard-inicio-cobros', function () {
    return $this->call('cobros:auditar-dashboard-inicio');
})->purpose('Alias: compara cobros.monto vs pivote como en dashboard de Inicio.');

Artisan::command('auditar-cobros-resumen', function () {
    return $this->call('cobros:auditar-resumen');
})->purpose('Alias: audita cobros_resumen vs totales recalculados desde cobros.');

Artisan::command('auditar-saldo-favor-recibos', function () {
    return $this->call('cobros:auditar-saldo-favor-recibos', ['--solo-con-saldo' => true]);
})->purpose('Alias: lista recibos que generaron saldo a favor.');

Artisan::command('auditar-monto-pagado-facturas', function () {
    return $this->call('cobros:auditar-monto-pagado-facturas', ['--solo-diferencias' => true]);
})->purpose('Alias: audita monto pagado vs pivote en facturas internas.');

Artisan::command('corregir-saldo-favor-facturas', function () {
    return $this->call('facturas:corregir-saldo-favor-aplicado', ['--fix' => true]);
})->purpose('Alias: corrige facturas con saldo a favor mal aplicado (sin IVA).');
