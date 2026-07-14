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
        $nombre = $this->normalizarNombre(trim($cliente->nombre.' '.$cliente->apellido));
        $documento = $this->normalizarDocumento((string) $cliente->cedula);

        return $this->parseDocumento($documento, $nombre);
    }

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
    public function parseDesdeDocumento(string $documento, string $nombre): array
    {
        return $this->parseDocumento(
            $this->normalizarDocumento($documento),
            $this->normalizarNombre($nombre),
        );
    }

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
    private function parseDocumento(string $documento, string $nombre): array
    {
        if ($this->esDocumentoConsumidorFinal($documento)) {
            return $this->receptorB2C(
                $nombre,
                $this->numeroDocumentoConsumidorFinal($documento),
            );
        }

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

        return $this->receptorB2C($nombre, $documento);
    }

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
    private function receptorB2C(string $nombre, string $dNumIDRec): array
    {
        return [
            'iNatRec' => 2,
            'iTiOpe' => 2,
            'iTiContRec' => null,
            'dRucRec' => null,
            'dDVRec' => null,
            'iTipIDRec' => 1,
            'dDTipIDRec' => 'Cédula paraguaya',
            'dNumIDRec' => $dNumIDRec !== '' ? $dNumIDRec : '0',
            'dNomRec' => $nombre,
        ];
    }

    /**
     * RUC genéricos usados como “sin RUC / consumidor final” (no son contribuyentes).
     * Si se envían como B2B, SIFEN suele rechazar por RUC inexistente (ej. 1306).
     */
    private function esDocumentoConsumidorFinal(string $documento): bool
    {
        $normalizado = strtoupper(str_replace(['.', ' '], '', $documento));

        return in_array($normalizado, [
            '9999999-9',
            '99999999-9',
            '9999999',
            '99999999',
            '999999999',
        ], true);
    }

    private function numeroDocumentoConsumidorFinal(string $documento): string
    {
        if ($ruc = $this->parseRuc($documento)) {
            return $ruc['ruc'];
        }

        $soloDigitos = preg_replace('/\D+/', '', $documento) ?? '';

        return $soloDigitos !== '' ? $soloDigitos : '0';
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

    private function normalizarNombre(string $nombre): string
    {
        $nombre = str_replace('_', ' ', trim($nombre));
        $nombre = preg_replace('/\s+/u', ' ', $nombre) ?? $nombre;

        return trim($nombre);
    }
}
