<?php

namespace App\Services\Sifen;

use App\Models\Cliente;

class SifenReceptorParser
{
    /**
     * @return array{
     *   iNatRec: int,
     *   iTiOpe: int,
     *   iTiContRec: ?int,
     *   dRucRec: ?string,
     *   dDVRec: ?int,
     *   iTipIDRec: ?int,
     *   dDTipIDRec: ?string,
     *   dNumIDRec: ?string,
     *   dNomRec: string,
     * }
     */
    public function parse(Cliente $cliente): array
    {
        $nombre = trim($cliente->nombre.' '.$cliente->apellido);
        $documento = $this->normalizarDocumento((string) $cliente->cedula);

        if ($ruc = $this->parseRuc($documento)) {
            return [
                'iNatRec' => 1,
                'iTiOpe' => 1,
                'iTiContRec' => $this->inferirTipoContribuyente($ruc['ruc']),
                'dRucRec' => $ruc['ruc'],
                'dDVRec' => $ruc['dv'],
                'iTipIDRec' => null,
                'dDTipIDRec' => null,
                'dNumIDRec' => null,
                'dNomRec' => $nombre,
            ];
        }

        return [
            'iNatRec' => 2,
            'iTiOpe' => 2,
            'iTiContRec' => null,
            'dRucRec' => null,
            'dDVRec' => null,
            'iTipIDRec' => 1,
            'dDTipIDRec' => 'Cédula paraguaya',
            'dNumIDRec' => $documento,
            'dNomRec' => $nombre,
        ];
    }

    private function normalizarDocumento(string $documento): string
    {
        return preg_replace('/\s+/', '', trim($documento)) ?? '';
    }

    /**
     * @return array{ruc: string, dv: int}|null
     */
    private function parseRuc(string $documento): ?array
    {
        if (preg_match('/^(\d{5,8})-(\d)$/', $documento, $m)) {
            return ['ruc' => $m[1], 'dv' => (int) $m[2]];
        }

        if (preg_match('/^(\d{8})(\d)$/', $documento, $m) && strlen($documento) === 9) {
            return ['ruc' => $m[1], 'dv' => (int) $m[2]];
        }

        return null;
    }

    private function inferirTipoContribuyente(string $ruc): int
    {
        return strlen($ruc) >= 8 ? 2 : 1;
    }
}
