<?php

namespace App\Services\Monitoreo;

use App\Models\Router;
use App\Services\Cloudflare\CloudflareDnsService;
use App\Services\MikroTikService;
use App\Support\IspFailoverConfig;
use App\Support\Nat11SalidaConfig;
use Illuminate\Support\Facades\Log;
use RouterOS\Query;
use Throwable;

class DedicadoSalidaService
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $reglasCache = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $natCache = [];

    public function __construct(
        private readonly MikroTikService $mikrotik,
        private readonly CloudflareDnsService $cloudflare,
    ) {}

    /**
     * @return array{modo: string, nombre: string, privada: string, publica: string, publica_ufinet: ?string, regla: bool, regla_ufinet: bool, nat_salida: bool, nat_entrada: bool, nat_salida_ufinet: bool, nat_entrada_ufinet: bool, mensaje: string}
     */
    public function snapshot(string $destino = Nat11SalidaConfig::DEDICADO): array
    {
        $perfil = Nat11SalidaConfig::perfil($destino);
        $modo = Nat11SalidaConfig::modo($destino);
        $regla = false;
        $reglaUfinet = false;
        $natSalida = false;
        $natEntrada = false;
        $natSalidaU = false;
        $natEntradaU = false;

        $router = IspFailoverConfig::router();
        if ($router) {
            try {
                $tigo = $this->buscarRegla($router, $perfil, 'tigo');
                $regla = (bool) ($tigo['existe'] ?? false) && ! ($tigo['disabled'] ?? true);
                if (($perfil['rule_ufinet'] ?? null) !== null) {
                    $ufi = $this->buscarRegla($router, $perfil, 'ufinet');
                    $reglaUfinet = (bool) ($ufi['existe'] ?? false) && ! ($ufi['disabled'] ?? true);
                }
                $natSalida = $this->natHabilitado($router, $perfil['nat_salida']);
                $natEntrada = $this->natHabilitado($router, $perfil['nat_entrada']);
                if (($perfil['nat_salida_ufinet'] ?? null) !== null) {
                    $natSalidaU = $this->natHabilitado($router, (string) $perfil['nat_salida_ufinet']);
                    $natEntradaU = $this->natHabilitado($router, (string) $perfil['nat_entrada_ufinet']);
                }
            } catch (Throwable $e) {
                Log::warning('[nat11-salida] no se pudo leer el borde', [
                    'destino' => $destino,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $pubU = $perfil['publica_ufinet'] ?? null;
        if ($modo === Nat11SalidaConfig::MODO_UFINET) {
            $mensaje = $pubU
                ? $perfil['nombre'].' usa NAT 1:1 Ufinet: '.$perfil['privada'].' ↔ '.$pubU.'.'
                : $perfil['nombre'].' sale por Ufinet (sin IP pública). Entrada a '.$perfil['publica'].' no funciona.';
        } else {
            $mensaje = $perfil['nombre'].' usa NAT 1:1 Tigo: '.$perfil['privada'].' ↔ '.$perfil['publica'].'.';
        }

        return [
            'modo' => $modo,
            'nombre' => $perfil['nombre'],
            'privada' => $perfil['privada'],
            'publica' => $perfil['publica'],
            'publica_ufinet' => $pubU,
            'regla' => $regla,
            'regla_ufinet' => $reglaUfinet,
            'nat_salida' => $natSalida,
            'nat_entrada' => $natEntrada,
            'nat_salida_ufinet' => $natSalidaU,
            'nat_entrada_ufinet' => $natEntradaU,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * @return array{ok: bool, message: string, modo?: string}
     */
    public function aplicar(string $modo, string $destino = Nat11SalidaConfig::DEDICADO): array
    {
        $destino = in_array($destino, Nat11SalidaConfig::destinos(), true)
            ? $destino
            : Nat11SalidaConfig::DEDICADO;
        $modo = $modo === Nat11SalidaConfig::MODO_UFINET
            ? Nat11SalidaConfig::MODO_UFINET
            : Nat11SalidaConfig::MODO_TIGO;
        $perfil = Nat11SalidaConfig::perfil($destino);

        $router = IspFailoverConfig::router();
        if (! $router) {
            return ['ok' => false, 'message' => 'No hay router de borde en Failover ISP.'];
        }

        try {
            $errores = $modo === Nat11SalidaConfig::MODO_UFINET
                ? $this->aplicarUfinet($router, $perfil)
                : $this->aplicarTigo($router, $perfil);
        } catch (Throwable $e) {
            Log::warning('[nat11-salida] error', [
                'destino' => $destino,
                'modo' => $modo,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'No se pudo aplicar en el borde: '.$e->getMessage()];
        }

        Nat11SalidaConfig::guardarModo($destino, $modo);

        $cfMsg = '';
        if ($destino === Nat11SalidaConfig::SERVER) {
            $ipDns = $modo === Nat11SalidaConfig::MODO_UFINET
                ? (string) ($perfil['publica_ufinet'] ?? $this->cloudflare->originUfinet())
                : (string) $perfil['publica'];
            $cf = $this->cloudflare->apuntar($ipDns);
            if (! ($cf['ok'] ?? false)) {
                $errores[] = $cf['message'] ?? 'Cloudflare no actualizó el DNS.';
            } else {
                $cfMsg = ' '.$cf['message'];
            }
        }

        if ($errores !== []) {
            return [
                'ok' => false,
                'message' => 'Cambio parcial ('.$perfil['nombre'].'): '.implode(' ', $errores),
                'modo' => $modo,
            ];
        }

        $pubU = (string) ($perfil['publica_ufinet'] ?? '');

        $base = $modo === Nat11SalidaConfig::MODO_UFINET
            ? ($pubU !== ''
                ? $perfil['nombre'].' por Ufinet con NAT 1:1 ('.$perfil['privada'].' ↔ '.$pubU.').'
                : $perfil['nombre'].' por Ufinet (sin IP pública). La 1:1 Tigo quedó desactivada.')
            : $perfil['nombre'].' por Tigo con NAT 1:1 ('.$perfil['privada'].' ↔ '.$perfil['publica'].').';

        return [
            'ok' => true,
            'modo' => $modo,
            'message' => $base.$cfMsg,
        ];
    }

    /**
     * @param  array<string, mixed>  $perfil
     * @return list<string>
     */
    private function aplicarTigo(Router $router, array $perfil): array
    {
        $errores = $this->asegurarLan($router, $perfil);
        $errores = array_merge($errores, $this->asegurarRegla($router, $perfil, 'tigo', disabled: false));
        if (($perfil['rule_ufinet'] ?? null) !== null) {
            $errores = array_merge($errores, $this->asegurarRegla($router, $perfil, 'ufinet', disabled: true));
        }
        $errores = array_merge($errores, $this->setNatDisabled($router, $perfil['nat_salida'], false));
        $errores = array_merge($errores, $this->setNatDisabled($router, $perfil['nat_entrada'], false));
        $errores = array_merge($errores, $this->aplicarNatUfinet($router, $perfil, disabled: true));

        return $errores;
    }

    /**
     * @param  array<string, mixed>  $perfil
     * @return list<string>
     */
    private function aplicarUfinet(Router $router, array $perfil): array
    {
        $errores = $this->asegurarLan($router, $perfil);
        $tigo = $this->buscarRegla($router, $perfil, 'tigo');
        if (($tigo['id'] ?? '') !== '') {
            $r = $this->mikrotik->apiSetDisabled($router, '/routing/rule/set', $tigo['id'], true);
            $this->olvidarCache();
            if (! ($r['success'] ?? false)) {
                $errores[] = 'Regla Tigo: '.($r['error'] ?? 'no se pudo desactivar');
            }
        }
        if (($perfil['rule_ufinet'] ?? null) !== null) {
            $errores = array_merge($errores, $this->asegurarRegla($router, $perfil, 'ufinet', disabled: false));
        }
        $errores = array_merge($errores, $this->setNatDisabled($router, $perfil['nat_salida'], true));
        $errores = array_merge($errores, $this->setNatDisabled($router, $perfil['nat_entrada'], true));
        $errores = array_merge($errores, $this->aplicarNatUfinet($router, $perfil, disabled: false));

        return $errores;
    }

    /**
     * @param  array<string, mixed>  $perfil
     * @return list<string>
     */
    private function aplicarNatUfinet(Router $router, array $perfil, bool $disabled): array
    {
        $salida = (string) ($perfil['nat_salida_ufinet'] ?? '');
        $entrada = (string) ($perfil['nat_entrada_ufinet'] ?? '');
        $publica = (string) ($perfil['publica_ufinet'] ?? '');
        if ($salida === '' || $entrada === '' || $publica === '') {
            return [];
        }

        $errores = [];
        $errores = array_merge($errores, $this->asegurarNat($router, [
            'comment' => $salida,
            'chain' => 'srcnat',
            'action' => 'netmap',
            'src-address' => $perfil['privada'],
            'to-addresses' => $publica,
            'place_before_pool' => true,
        ], $disabled));
        $errores = array_merge($errores, $this->asegurarNat($router, [
            'comment' => $entrada,
            'chain' => 'dstnat',
            'action' => 'netmap',
            'dst-address' => $publica,
            'to-addresses' => $perfil['privada'],
            'place_before_pool' => false,
        ], $disabled));

        return $errores;
    }

    /**
     * @param  array<string, mixed>  $perfil
     * @return list<string>
     */
    private function asegurarLan(Router $router, array $perfil): array
    {
        $errores = [];
        $catchAll = $this->buscarRegla($router, $perfil, 'tigo');
        if (($catchAll['id'] ?? '') === '') {
            $catchAll = $this->buscarRegla($router, $perfil, 'ufinet');
        }

        foreach ($perfil['lan'] ?? [] as $lan) {
            $comment = (string) ($lan['comment'] ?? '');
            $dst = (string) ($lan['dst'] ?? '');
            if ($comment === '' || $dst === '') {
                continue;
            }
            $existente = $this->buscarReglaPorComment($router, $comment);
            if (($existente['id'] ?? '') !== '') {
                if ($existente['disabled'] ?? true) {
                    $r = $this->mikrotik->apiSetDisabled($router, '/routing/rule/set', $existente['id'], false);
                    $this->olvidarCache();
                    if (! ($r['success'] ?? false)) {
                        $errores[] = $comment.': '.($r['error'] ?? 'no se pudo activar');
                    }
                }
                if (($catchAll['id'] ?? '') !== '') {
                    $this->mikrotik->apiMove($router, '/routing/rule/move', $existente['id'], $catchAll['id']);
                    $this->olvidarCache();
                }

                continue;
            }

            $equals = [
                'src-address' => $perfil['privada'].'/32',
                'dst-address' => $dst,
                'action' => 'lookup',
                'table' => 'main',
                'comment' => $comment,
                'disabled' => 'no',
            ];
            if (($catchAll['id'] ?? '') !== '') {
                $equals['place-before'] = $catchAll['id'];
            }
            $r = $this->mikrotik->apiAdd($router, '/routing/rule/add', $equals);
            $this->olvidarCache();
            if (! ($r['success'] ?? false)) {
                $errores[] = 'No se pudo crear '.$comment.': '.($r['error'] ?? 'error');
            }
        }

        return $errores;
    }

    /**
     * @param  array<string, mixed>  $perfil
     * @return list<string>
     */
    private function asegurarRegla(Router $router, array $perfil, string $cual, bool $disabled): array
    {
        $comment = $cual === 'ufinet'
            ? (string) ($perfil['rule_ufinet'] ?? '')
            : (string) ($perfil['rule_tigo'] ?? '');
        if ($comment === '') {
            return [];
        }

        $regla = $this->buscarRegla($router, $perfil, $cual);
        if (($regla['id'] ?? '') !== '') {
            $r = $this->mikrotik->apiSetDisabled($router, '/routing/rule/set', $regla['id'], $disabled);
            $this->olvidarCache();
            if (! ($r['success'] ?? false)) {
                return ['Regla '.$cual.': '.($r['error'] ?? 'no se pudo actualizar')];
            }

            return [];
        }

        $tabla = $cual === 'ufinet'
            ? (string) $perfil['tabla_ufinet']
            : (string) $perfil['tabla_tigo'];

        $r = $this->mikrotik->apiAdd($router, '/routing/rule/add', [
            'src-address' => $perfil['privada'].'/32',
            'action' => 'lookup',
            'table' => $tabla,
            'comment' => $comment,
            'disabled' => $disabled ? 'yes' : 'no',
        ]);
        $this->olvidarCache();
        if (! ($r['success'] ?? false)) {
            return ['No se pudo crear la regla '.$tabla.': '.($r['error'] ?? 'error')];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $perfil
     * @return array{id: string, disabled: bool, existe: bool}
     */
    private function buscarRegla(Router $router, array $perfil, string $cual): array
    {
        $comment = $cual === 'ufinet'
            ? (string) ($perfil['rule_ufinet'] ?? '')
            : (string) ($perfil['rule_tigo'] ?? '');
        if ($comment !== '') {
            $byComment = $this->buscarReglaPorComment($router, $comment);
            if (($byComment['id'] ?? '') !== '') {
                return $byComment + ['existe' => true];
            }
        }

        if ($cual === 'tigo' && ($perfil['match_src'] ?? false)) {
            foreach ($this->listarReglas($router) as $row) {
                $src = (string) ($row['src-address'] ?? '');
                $tabla = (string) ($row['table'] ?? '');
                if ($src !== $perfil['privada'].'/32' && $src !== $perfil['privada']) {
                    continue;
                }
                if ($tabla !== '' && $tabla !== (string) $perfil['tabla_tigo']) {
                    continue;
                }

                return [
                    'id' => (string) ($row['.id'] ?? ''),
                    'disabled' => $this->rosYes($row['disabled'] ?? 'no'),
                    'existe' => true,
                ];
            }
        }

        return ['id' => '', 'disabled' => true, 'existe' => false];
    }

    /**
     * @return array{id: string, disabled: bool, existe: bool}
     */
    private function buscarReglaPorComment(Router $router, string $comment): array
    {
        foreach ($this->listarReglas($router) as $row) {
            if (trim((string) ($row['comment'] ?? '')) !== $comment) {
                continue;
            }

            return [
                'id' => (string) ($row['.id'] ?? ''),
                'disabled' => $this->rosYes($row['disabled'] ?? 'no'),
                'existe' => true,
            ];
        }

        return ['id' => '', 'disabled' => true, 'existe' => false];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarReglas(Router $router): array
    {
        $key = (string) $router->router_id;
        if (isset($this->reglasCache[$key])) {
            return $this->reglasCache[$key];
        }

        $client = $this->mikrotik->connect($router);
        $rows = $client->query((new Query('/routing/rule/print'))
            ->equal('.proplist', '.id,src-address,dst-address,action,table,comment,disabled'))
            ->read();
        $this->mikrotik->disconnect();

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $this->reglasCache[$key] = $out;
    }

    private function olvidarCache(): void
    {
        $this->reglasCache = [];
        $this->natCache = [];
    }

    private function natHabilitado(Router $router, string $comment): bool
    {
        $nat = $this->buscarNat($router, $comment);

        return ($nat['id'] ?? '') !== '' && ! ($nat['disabled'] ?? true);
    }

    /**
     * @param  array{comment: string, chain: string, action: string, src-address?: string, dst-address?: string, to-addresses: string, place_before_pool?: bool}  $spec
     * @return list<string>
     */
    private function asegurarNat(Router $router, array $spec, bool $disabled): array
    {
        $comment = $spec['comment'];
        $existente = $this->buscarNat($router, $comment);
        if (($existente['id'] ?? '') !== '') {
            return $this->setNatDisabled($router, $comment, $disabled);
        }

        $equals = [
            'chain' => $spec['chain'],
            'action' => $spec['action'],
            'to-addresses' => $spec['to-addresses'],
            'comment' => $comment,
            'disabled' => $disabled ? 'yes' : 'no',
        ];
        if (! empty($spec['src-address'])) {
            $equals['src-address'] = (string) $spec['src-address'];
        }
        if (! empty($spec['dst-address'])) {
            $equals['dst-address'] = (string) $spec['dst-address'];
        }
        if (! empty($spec['place_before_pool'])) {
            $pool = $this->idNatPoolUfinet($router);
            if ($pool !== '') {
                $equals['place-before'] = $pool;
            }
        }

        $r = $this->mikrotik->apiAdd($router, '/ip/firewall/nat/add', $equals);
        $this->olvidarCache();
        if (! ($r['success'] ?? false)) {
            return ['No se pudo crear '.$comment.': '.($r['error'] ?? 'error')];
        }

        return [];
    }

    private function idNatPoolUfinet(Router $router): string
    {
        foreach ($this->listarNat($router) as $row) {
            if (trim((string) ($row['comment'] ?? '')) !== 'NAT-UFINET') {
                continue;
            }
            if ((string) ($row['chain'] ?? '') !== 'srcnat') {
                continue;
            }
            if (trim((string) ($row['src-address'] ?? '')) !== '') {
                continue;
            }
            $to = (string) ($row['to-addresses'] ?? '');
            if (! str_contains($to, '-')) {
                continue;
            }

            return (string) ($row['.id'] ?? '');
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function setNatDisabled(Router $router, string $comment, bool $disabled): array
    {
        $nat = $this->buscarNat($router, $comment);
        if (($nat['id'] ?? '') === '') {
            return ['No está la NAT «'.$comment.'».'];
        }
        $r = $this->mikrotik->apiSetDisabled($router, '/ip/firewall/nat/set', $nat['id'], $disabled);
        $this->olvidarCache();
        if (! ($r['success'] ?? false)) {
            return [$comment.': '.($r['error'] ?? 'error')];
        }

        return [];
    }

    /**
     * @return array{id: string, disabled: bool}
     */
    private function buscarNat(Router $router, string $comment): array
    {
        foreach ($this->listarNat($router) as $row) {
            if (trim((string) ($row['comment'] ?? '')) !== $comment) {
                continue;
            }

            return [
                'id' => (string) ($row['.id'] ?? ''),
                'disabled' => $this->rosYes($row['disabled'] ?? 'no'),
            ];
        }

        return ['id' => '', 'disabled' => true];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarNat(Router $router): array
    {
        $key = (string) $router->router_id;
        if (isset($this->natCache[$key])) {
            return $this->natCache[$key];
        }

        $client = $this->mikrotik->connect($router);
        $q = (new Query('/ip/firewall/nat/print'))
            ->equal('.proplist', '.id,chain,action,src-address,dst-address,to-addresses,comment,disabled');
        $rows = $client->query($q)->read();
        $this->mikrotik->disconnect();
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $this->natCache[$key] = $out;
    }

    private function rosYes(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['yes', 'true', '1'], true);
    }
}
