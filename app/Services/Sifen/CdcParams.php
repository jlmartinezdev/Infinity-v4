<?php

namespace App\Services\Sifen;

use Carbon\CarbonInterface;

class CdcParams
{
    public function __construct(
        public int $tipoDocumento,
        public string $ruc,
        public int $dvRuc,
        public int $establecimiento,
        public int $puntoExpedicion,
        public int $numeroDocumento,
        public int $tipoContribuyente,
        public CarbonInterface $fechaEmision,
        public int $tipoEmision,
        public int $codigoSeguridad,
    ) {}
}
