<?php

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SifenConfiguracion;
use App\Services\Sifen\SifenQrGenerator;

$path = __DIR__.'/xml/DE_firmado_01052639347001001000002312026061815875041056_20260618175423.xml';
$xml = file_get_contents($path);

preg_match('/<dCarQR>(.*?)<\/dCarQR>/s', $xml, $m);
$urlEnXml = html_entity_decode($m[1] ?? '', ENT_XML1);

echo "URL en XML (decodificada):\n$urlEnXml\n\n";

$config = SifenConfiguracion::activa();
echo 'CSC ID BD: '.$config?->cscIdEfectivo()."\n";
echo 'CSC token BD: '.($config?->cscTokenEfectivo() ? substr($config->cscTokenEfectivo(), 0, 4).'...'.substr($config->cscTokenEfectivo(), -4).' (len='.strlen($config->cscTokenEfectivo()).')' : 'VACIO')."\n\n";

$gen = app(SifenQrGenerator::class);
$urlRecalc = $gen->construirUrlDesdeXmlFirmado($xml, $config);

echo "URL recalculada:\n$urlRecalc\n\n";
echo 'Coinciden: '.($urlEnXml === $urlRecalc ? 'SI' : 'NO')."\n";

if ($urlEnXml !== $urlRecalc) {
    parse_str(parse_url($urlEnXml, PHP_URL_QUERY), $q1);
    parse_str(parse_url($urlRecalc, PHP_URL_QUERY), $q2);
    foreach (['cHashQR', 'DigestValue', 'dFeEmiDE', 'IdCSC'] as $k) {
        if (($q1[$k] ?? '') !== ($q2[$k] ?? '')) {
            echo "Diff $k: xml={$q1[$k]} recalc={$q2[$k]}\n";
        }
    }
}

// Probar con CSC ejemplo DNIT
$tokenTest = 'ABCD0000000000000000000000000000';
if (preg_match('/cHashQR=([a-f0-9]+)/', $urlEnXml, $hm)) {
    $hashEnXml = $hm[1];
    $base = strstr($urlEnXml, '?');
    $paso1 = substr($base, 1, strrpos($base, '&cHashQR=') - 1);
    $hashTest = hash('sha256', $paso1.$tokenTest);
    echo "\nHash con CSC ejemplo DNIT: $hashTest\n";
    echo 'Match ejemplo: '.($hashEnXml === $hashTest ? 'SI' : 'NO')."\n";
}

if ($config?->cscTokenEfectivo()) {
    $hashBd = hash('sha256', $paso1.$config->cscTokenEfectivo());
    echo 'Hash con CSC BD: '.$hashBd."\n";
    echo 'Match BD: '.($hashEnXml === $hashBd ? 'SI' : 'NO')."\n";
}
