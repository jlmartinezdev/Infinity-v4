<?php

namespace App\Services\Portal;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PortalReferidosService
{
    public function __construct(
        private readonly PortalAppConfigService $config
    ) {}

    /**
     * @return array{codigo: string, link: string, mensaje_share: string, mensaje: string, share_text: string, invitados: int, puntos_por_alta: int, puntos_ganados: int}
     */
    public function resumen(Cliente $cliente): array
    {
        $codigo = $this->asegurarCodigo($cliente);
        $invitados = Cliente::query()
            ->where('referido_por_cliente_id', $cliente->cliente_id)
            ->count();
        $ref = $this->config->referidos();
        $ptsAlta = (int) ($ref['puntos_por_alta'] ?? 50);
        $linkBase = rtrim((string) ($ref['link_base'] ?? ''), '/');
        if ($linkBase === '') {
            $linkBase = rtrim((string) config('app.url'), '/').'/r';
        }
        $link = $linkBase.'/'.$codigo;
        $mensaje = "Te invito a Interplus. Usá mi código {$codigo}";

        return [
            'codigo' => $codigo,
            'link' => $link,
            'mensaje_share' => $mensaje,
            'mensaje' => $mensaje,
            'share_text' => $mensaje,
            'invitados' => $invitados,
            'puntos_por_alta' => $ptsAlta,
            'puntos_ganados' => $invitados * $ptsAlta,
        ];
    }

    public function asegurarCodigo(Cliente $cliente): string
    {
        if (filled($cliente->referido_codigo)) {
            return (string) $cliente->referido_codigo;
        }

        $codigo = $this->generarCodigoUnico((int) $cliente->cliente_id);
        $cliente->referido_codigo = $codigo;
        $cliente->save();

        return $codigo;
    }

    /**
     * Aplica un código de referido al cliente actual (una sola vez).
     *
     * @return array{ok: bool, referido_por: string|null}
     */
    public function canjear(Cliente $cliente, string $codigo): array
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            throw ValidationException::withMessages(['codigo' => ['Indicá un código de referido.']]);
        }

        if (filled($cliente->referido_por_cliente_id)) {
            throw ValidationException::withMessages(['codigo' => ['Ya registraste un código de referido.']]);
        }

        $this->asegurarCodigo($cliente);
        if (strcasecmp((string) $cliente->referido_codigo, $codigo) === 0) {
            throw ValidationException::withMessages(['codigo' => ['No podés usar tu propio código.']]);
        }

        $referente = Cliente::query()->where('referido_codigo', $codigo)->first();
        if (! $referente) {
            throw ValidationException::withMessages(['codigo' => ['Código de referido no válido.']]);
        }

        DB::transaction(function () use ($cliente, $referente) {
            $fresh = Cliente::query()->lockForUpdate()->findOrFail($cliente->cliente_id);
            if ($fresh->referido_por_cliente_id) {
                throw ValidationException::withMessages(['codigo' => ['Ya registraste un código de referido.']]);
            }
            $fresh->referido_por_cliente_id = $referente->cliente_id;
            $fresh->save();
        });

        return [
            'ok' => true,
            'referido_por' => $referente->referido_codigo,
        ];
    }

    private function generarCodigoUnico(int $clienteId): string
    {
        $base = 'IP-'.strtoupper(str_pad(base_convert((string) max(1, $clienteId), 10, 36), 6, '0', STR_PAD_LEFT));
        if (! Cliente::query()->where('referido_codigo', $base)->exists()) {
            return $base;
        }

        for ($i = 0; $i < 20; $i++) {
            $try = 'IP-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            if (! Cliente::query()->where('referido_codigo', $try)->exists()) {
                return $try;
            }
        }

        return 'IP-'.strtoupper(substr(md5((string) $clienteId.microtime()), 0, 6));
    }
}
