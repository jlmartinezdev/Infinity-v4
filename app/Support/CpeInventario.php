<?php

namespace App\Support;

use App\Models\CpeModelo;
use App\Models\Servicio;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class CpeInventario
{
    public const OTRO = '__otro__';

    public static function accesos(): array
    {
        return config('cpe.accesos', []);
    }

    public static function onus(): array
    {
        return self::modelos('onu');
    }

    public static function routers(): array
    {
        return self::modelos('router');
    }

    public static function antenas(): array
    {
        return self::modelos('antena');
    }

    /**
     * Catálogo para el select, incluyendo el valor actual si ya no está activo.
     *
     * @return array<string, string>
     */
    public static function opciones(string $tipo, ?string $actual = null): array
    {
        $mapa = self::modelos($tipo);
        if ($actual && $actual !== self::OTRO && ! isset($mapa[$actual])) {
            $mapa[$actual] = $actual;
        }

        return $mapa;
    }

    public static function keysAcceso(): array
    {
        return array_keys(self::accesos());
    }

    public static function keysOnu(): array
    {
        return array_keys(self::onus());
    }

    public static function keysRouter(): array
    {
        return array_keys(self::routers());
    }

    public static function keysAntena(): array
    {
        return array_keys(self::antenas());
    }

    public static function etiquetaAcceso(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        return self::accesos()[$key] ?? $key;
    }

    public static function etiquetaOnu(?string $key): ?string
    {
        return self::etiquetaDe(self::onus(), $key);
    }

    public static function etiquetaRouter(?string $key): ?string
    {
        return self::etiquetaDe(self::routers(), $key);
    }

    public static function etiquetaAntena(?string $key): ?string
    {
        return self::etiquetaDe(self::antenas(), $key);
    }

    /**
     * Si eligieron "Otro modelo", lo crea (o reutiliza) y devuelve la clave a guardar.
     */
    public static function resolverSeleccion(string $tipo, ?string $clave, ?string $nombreOtro): ?string
    {
        $clave = trim((string) $clave);
        $nombreOtro = trim((string) $nombreOtro);

        if ($clave === '' || $clave === self::OTRO) {
            if ($nombreOtro === '') {
                return null;
            }

            return self::asegurarModelo($tipo, $nombreOtro);
        }

        if ($nombreOtro !== '' && ! isset(self::modelos($tipo)[$clave])) {
            return self::asegurarModelo($tipo, $nombreOtro);
        }

        return $clave;
    }

    public static function asegurarModelo(string $tipo, string $nombre): string
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return '';
        }

        if (! self::tablaLista()) {
            return Str::limit(Str::slug($nombre, '_') ?: 'modelo', 32, '');
        }

        $existente = CpeModelo::query()
            ->where('tipo', $tipo)
            ->where(function ($q) use ($nombre) {
                $q->where('nombre', $nombre)
                    ->orWhere('clave', Str::slug($nombre, '_'));
            })
            ->first();
        if ($existente) {
            if (! $existente->activo) {
                $existente->update(['activo' => true]);
            }

            return $existente->clave;
        }

        $clave = Str::slug($nombre, '_') ?: 'modelo';
        $clave = substr($clave, 0, 32);
        $base = $clave;
        $n = 1;
        while (CpeModelo::where('tipo', $tipo)->where('clave', $clave)->exists()) {
            $n++;
            $sufijo = '_'.$n;
            $clave = substr($base, 0, 32 - strlen($sufijo)).$sufijo;
        }

        CpeModelo::create([
            'tipo' => $tipo,
            'clave' => $clave,
            'nombre' => Str::limit($nombre, 64, ''),
            'activo' => true,
        ]);

        return $clave;
    }

    /**
     * Texto corto para NOC: "LiteBeam AC + TP-Link 840 · ACS" / "Huawei ONU · SSH".
     */
    public static function resumen(Servicio $servicio): ?string
    {
        $partes = [];
        $antena = self::etiquetaAntena($servicio->cpe_antena ?? null);
        $onu = self::etiquetaOnu($servicio->cpe_onu ?? null);
        $router = self::etiquetaRouter($servicio->cpe_router ?? null);
        if ($antena) {
            $partes[] = $antena;
        }
        if ($onu) {
            $partes[] = $onu;
        }
        if ($router) {
            $partes[] = $router;
        }
        $acceso = self::etiquetaAcceso($servicio->cpe_acceso ?? null);
        if ($partes === [] && ! $acceso) {
            return null;
        }
        $txt = $partes !== [] ? implode(' + ', $partes) : 'CPE en casa';
        if ($acceso) {
            $txt .= ' · '.$acceso;
        }

        return $txt;
    }

    public static function usaAcs(Servicio $servicio): bool
    {
        $acceso = (string) ($servicio->cpe_acceso ?? '');
        if ($acceso === 'acs') {
            return true;
        }
        if ($acceso === 'ssh') {
            return false;
        }

        return filled($servicio->tr069_serial);
    }

    public static function usaSshCpe(Servicio $servicio): bool
    {
        return (string) ($servicio->cpe_acceso ?? '') === 'ssh';
    }

    /**
     * @return array<string, string>
     */
    private static function modelos(string $tipo): array
    {
        if (self::tablaLista()) {
            $filas = CpeModelo::query()
                ->where('tipo', $tipo)
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['clave', 'nombre']);
            if ($filas->isNotEmpty()) {
                return $filas->mapWithKeys(fn (CpeModelo $m) => [$m->clave => $m->nombre])->all();
            }
        }

        return (array) config('cpe.'.$tipo, []);
    }

    private static function tablaLista(): bool
    {
        try {
            return Schema::hasTable('cpe_modelos');
        } catch (\Throwable) {
            return false;
        }
    }

    private static function etiquetaDe(array $mapa, ?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        return $mapa[$key] ?? $key;
    }
}
