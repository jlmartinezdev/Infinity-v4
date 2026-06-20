<?php

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Factura;
use App\Services\Sifen\SifenService;

$factura = Factura::where('estado', 'borrador')->orderByDesc('id')->first();
if (! $factura) {
    echo "Sin borradores\n";
    exit(1);
}

echo 'Factura #'.$factura->id."\n";

try {
    $sifen = app(SifenService::class);
    $prep = $sifen->prepararDocumento($factura, false);
    $signer = app(App\Services\Sifen\SifenXmlSigner::class);
    $firmado = $signer->firmar($prep['xml'], $prep['cdc']);
    echo "Firma OK\n";
    echo 'Digest: '.$firmado['digest_value']."\n";
    echo 'default: '.(str_contains($firmado['xml'], 'default:') ? 'SI' : 'NO')."\n";
    echo 'Signature xmlns: '.(str_contains($firmado['xml'], '<Signature xmlns=') ? 'SI' : 'NO')."\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    exit(1);
}
