<?php

namespace App\Services\WhatsApp;

use App\Models\TvCuenta;
use App\Models\WhatsappMensaje;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Crea una cuenta TV cuando el mensaje trae credenciales Lumix o Suffixed.
 *
 * Formato: (Lumix: cuenta@tv.es, xxxxx) o (Suffixed: cuenta@tv.es, xxxxx)
 */
class WhatsAppTvCuentaInboundService
{
    public const PRECIO_PERFIL = 20000.0;

    /**
     * @return array{aplicacion: string, usuario_app: string, password: string}|null
     */
    public static function parsear(string $texto): ?array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }

        if (! preg_match(
            '/\(?\s*(lumix|suffixed)\s*:\s*([^,\n]+)\s*,\s*([^\n)]+)\)?/iu',
            $texto,
            $m
        )) {
            return null;
        }

        $marca = mb_strtolower(trim($m[1]));
        $usuario = trim($m[2]);
        $password = trim($m[3]);

        if ($usuario === '' || $password === '') {
            return null;
        }

        return [
            'aplicacion' => $marca === 'lumix' ? TvCuenta::APP_LUMIX : TvCuenta::APP_SUFFIXED,
            'usuario_app' => $usuario,
            'password' => $password,
        ];
    }

    public static function nombreBase(string $aplicacion, Carbon $fecha): string
    {
        $prefijo = $aplicacion === TvCuenta::APP_LUMIX ? 'Lumix' : 'Suffixed';

        return $prefijo.'_'.$fecha->format('d-m-Y');
    }

    /**
     * @param  array{aplicacion: string, usuario_app: string, password: string}  $parsed
     * @return array<string, mixed>
     */
    public static function armarAtributos(array $parsed, Carbon $hoy, string $nombre): array
    {
        $vencimiento = $hoy->copy()->addMonthNoOverflow();
        $precio = self::PRECIO_PERFIL;
        $esLumix = $parsed['aplicacion'] === TvCuenta::APP_LUMIX;

        $attrs = [
            'nombre' => $nombre,
            'aplicacion' => $parsed['aplicacion'],
            'usuario_app' => $parsed['usuario_app'],
            'password' => $parsed['password'],
            'vencimiento_pago' => $vencimiento->toDateString(),
            'dia_aviso_vencimiento' => $vencimiento->day,
            'notas' => 'Creada automáticamente desde WhatsApp.',
        ];

        if ($esLumix) {
            $attrs['perfil_1'] = null;
            $attrs['perfil_2'] = null;
            $attrs['perfil_3'] = null;
            $attrs['precio_perfil_1'] = null;
            $attrs['precio_perfil_2'] = null;
            $attrs['precio_perfil_3'] = null;
            $attrs['precio_pantalla_1'] = $precio;
            $attrs['precio_pantalla_2'] = $precio;
            $attrs['precio_pantalla_3'] = $precio;
            $attrs['precio_pantalla_4'] = $precio;
        } else {
            $attrs['perfil_1'] = 'Perfil 1';
            $attrs['perfil_2'] = 'Perfil 2';
            $attrs['perfil_3'] = 'Perfil 3';
            $attrs['precio_perfil_1'] = $precio;
            $attrs['precio_perfil_2'] = $precio;
            $attrs['precio_perfil_3'] = $precio;
            $attrs['precio_pantalla_1'] = null;
            $attrs['precio_pantalla_2'] = null;
            $attrs['precio_pantalla_3'] = null;
            $attrs['precio_pantalla_4'] = null;
        }

        return $attrs;
    }

    /**
     * @return bool true si el mensaje era de credenciales TV (aunque no se cree por duplicado)
     */
    public function intentar(WhatsappMensaje $mensaje): bool
    {
        if (! $mensaje->esEntrada()) {
            return false;
        }

        $parsed = self::parsear((string) $mensaje->cuerpo);
        if ($parsed === null) {
            return false;
        }

        try {
            $cuenta = $this->registrar($parsed, $mensaje);
            if ($cuenta && $mensaje->exists) {
                $mensaje->contexto_tipo = 'tv_cuenta';
                $mensaje->contexto_id = $cuenta->id;
                $mensaje->save();
            }
        } catch (\Throwable $e) {
            Log::error('[WhatsApp TV] No se pudo crear la cuenta: '.$e->getMessage(), [
                'whatsapp_mensaje_id' => $mensaje->id,
                'usuario_app' => $parsed['usuario_app'],
                'exception' => $e,
            ]);
        }

        return true;
    }

    /**
     * @param  array{aplicacion: string, usuario_app: string, password: string}  $parsed
     */
    public function registrar(array $parsed, ?WhatsappMensaje $mensaje = null): ?TvCuenta
    {
        $existente = TvCuenta::query()
            ->where('usuario_app', $parsed['usuario_app'])
            ->first();
        if ($existente) {
            Log::info('[WhatsApp TV] Cuenta ya existía, no se duplicó', [
                'tv_cuenta_id' => $existente->id,
                'usuario_app' => $parsed['usuario_app'],
            ]);

            return $existente;
        }

        $hoy = Carbon::today();
        $nombre = $this->nombreUnico(self::nombreBase($parsed['aplicacion'], $hoy));
        $attrs = self::armarAtributos($parsed, $hoy, $nombre);
        if ($mensaje?->telefono) {
            $attrs['notas'] = 'Creada automáticamente desde WhatsApp '.$mensaje->telefono.'.';
        }

        $cuenta = TvCuenta::create($attrs);

        Log::info('[WhatsApp TV] Cuenta creada', [
            'tv_cuenta_id' => $cuenta->id,
            'nombre' => $cuenta->nombre,
            'aplicacion' => $cuenta->aplicacion,
            'usuario_app' => $cuenta->usuario_app,
        ]);

        return $cuenta;
    }

    private function nombreUnico(string $base): string
    {
        $nombre = $base;
        $n = 2;
        while (TvCuenta::query()->where('nombre', $nombre)->exists()) {
            $nombre = $base.'_'.$n;
            $n++;
        }

        return $nombre;
    }
}
