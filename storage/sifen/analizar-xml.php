<?php

require __DIR__.'/../../vendor/autoload.php';

use App\Services\Sifen\SifenXmlManipulator;
use RobRichards\XMLSecLibs\XMLSecurityDSig;

$files = [
    'DE_firmado_01052639347001001000002312026061815875041056_20260618175423.xml',
    'DE_firmado_01052639347001001000002212026061812302460685_20260618174751.xml',
    'DE_firmado_01052639347001001000002112026061815461642845_20260618174335.xml',
    'DE_firmado_000013_QR_FIXED.xml',
];

foreach ($files as $f) {
    $path = __DIR__.'/xml/'.$f;
    if (! is_file($path)) {
        echo "$f: NO EXISTE\n";
        continue;
    }
    $x = file_get_contents($path);
    echo "=== $f ===\n";
    echo 'default: '.(str_contains($x, 'default:') ? 'SI' : 'NO')."\n";
    echo 'Signature xmlns: '.(str_contains($x, '<Signature xmlns=') ? 'SI' : 'NO')."\n";

    try {
        SifenXmlManipulator::assertEstructuraFirmaValida($x);
        echo "assert estructura: OK\n";
    } catch (Throwable $e) {
        echo 'assert estructura: '.$e->getMessage()."\n";
    }

    $dom = new DOMDocument;
    if ($dom->loadXML($x)) {
        $objDSig = new XMLSecurityDSig;
        if ($objDSig->locateSignature($dom)) {
            $objKey = $objDSig->locateKey($objDSig->sigNode);
            if ($objKey) {
                $certPath = __DIR__.'/cert/certificado.p12';
                if (is_readable($certPath)) {
                    $app = require __DIR__.'/../../bootstrap/app.php';
                    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
                    $mat = app(App\Services\Sifen\SifenCertificadoService::class)->cargarDesdeP12();
                    $objKey->loadKey($mat['cert'], false, true);
                    $v = $objDSig->verify($objKey);
                    echo 'verify local: '.($v === 1 ? 'OK' : 'FALLÓ ('.$v.')')."\n";
                } else {
                    echo "verify local: (sin acceso cert)\n";
                }
            }
        }
    }

    $p = strpos($x, '</DE>');
    if ($p !== false) {
        echo substr($x, $p, min(180, strlen($x) - $p))."\n";
    }
    if (preg_match('/<CanonicalizationMethod Algorithm="([^"]+)"/', $x, $cm)) {
        echo 'Canon SignedInfo: '.$cm[1]."\n";
    }
    if (preg_match_all('/<Transform Algorithm="([^"]+)"/', $x, $tm)) {
        echo 'Transforms: '.implode(', ', $tm[1])."\n";
    }
    echo "\n";
}
