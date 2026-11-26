<?php

use App\Models\Servicio;
use App\Services\Huawei\HuaweiOnuService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$payload = json_decode((string) stream_get_contents(STDIN), true);
if (! is_array($payload)) {
    echo json_encode(['success' => false, 'message' => 'La lectura web no recibió datos.']);
    exit(1);
}

$servicio = Servicio::query()->find((int) ($payload['servicio_id'] ?? 0));
if (! $servicio) {
    echo json_encode(['success' => false, 'message' => 'No se encontró el servicio.']);
    exit(1);
}

$huawei = $app->make(HuaweiOnuService::class);
$op = (string) ($payload['op'] ?? '');
$result = match ($op) {
    'leerWifi' => $huawei->leerWifi($servicio),
    'leerOptica' => $huawei->leerOptica($servicio),
    'reiniciar' => $huawei->reiniciar($servicio),
    'configurarIpv6' => $huawei->configurarIpv6($servicio),
    'listarConectados' => $huawei->listarConectados($servicio),
    'listarDhcpLeases' => $huawei->listarDhcpLeases($servicio),
    'configurarHotspot' => $huawei->configurarHotspot($servicio),
    'cambiarWifi' => $huawei->cambiarWifi(
        $servicio,
        (string) ($payload['ssid'] ?? ''),
        (string) ($payload['password'] ?? '')
    ),
    default => ['success' => false, 'message' => 'Operación web desconocida.'],
};

unset($result['password'], $result['clave']);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
