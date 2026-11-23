<?php

namespace App\Services\Sorteo;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class SorteoQr
{
    /**
     * @return array{url: string, svg: string}
     */
    public static function deCliente(int $clienteId): array
    {
        $url = 'https://interplus.com.py/sorteo/'.$clienteId;
        $opciones = new QROptions([
            'outputBase64' => false,
            'scale' => 4,
            'quietzoneSize' => 2,
        ]);
        $svg = (new QRCode($opciones))->render($url);
        $svg = str_replace('<svg ', '<svg width="120" height="120" style="display:block;margin:0 auto;" ', $svg);

        return ['url' => $url, 'svg' => $svg];
    }
}
