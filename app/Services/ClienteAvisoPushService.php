<?php

namespace App\Services;

use App\Models\FacturaInterna;
use App\Models\PushAviso;
use App\Models\PushAvisoEntrega;
use App\Models\Servicio;
use App\Models\User;
use App\Support\PushAvisoPlantilla;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Envío voluntario de push FCM a clientes (promos / avisos desde el panel).
 */
class ClienteAvisoPushService
{
    public function __construct(
        protected FcmPushService $fcm
    ) {}

    /**
     * Cantidad de clientes portal activos con token FCM.
     */
    public function contarConPush(): int
    {
        return count($this->idsConPush());
    }

    /**
     * Conteos de cada grupo masivo (clientes con app).
     *
     * @return array<string, int>
     */
    public function conteos(int $dias): array
    {
        $dias = $this->normalizarDias($dias);
        $pushIds = $this->idsConPush();
        $cuentas = $this->mapaCuentas($pushIds);
        $vencidas = 0;
        foreach ($cuentas as $cuenta) {
            if (($cuenta['vencidas'] ?? 0) > 0) {
                $vencidas++;
            }
        }

        return [
            'todos' => count($pushIds),
            'pendiente_pago' => count($cuentas),
            'vencidas' => $vencidas,
            'por_vencer' => count($this->idsPorVencer($pushIds, $dias)),
            'suspendidos' => count($this->idsServicio($pushIds, Servicio::ESTADO_SUSPENDIDO)),
            'cortados' => count($this->idsServicio($pushIds, Servicio::ESTADO_CORTADO)),
        ];
    }

    public function contarDestino(string $destino, ?int $dias = null): int
    {
        $dias = $this->normalizarDias($dias);
        if (! array_key_exists($destino, PushAviso::destinos()) || $destino === 'seleccionados') {
            return 0;
        }
        if ($destino === 'todos') {
            return count($this->idsConPush());
        }

        return count($this->idsQueCoinciden($destino, $this->idsConPush(), $dias));
    }

    /**
     * @return Collection<int, User>
     */
    public function destinatariosTodos(): Collection
    {
        return $this->usuariosDestino('todos', [], 3);
    }

    /**
     * @param  list<int>  $clienteIds
     * @return Collection<int, User>
     */
    public function destinatariosSeleccionados(array $clienteIds): Collection
    {
        return $this->usuariosDestino('seleccionados', $clienteIds, 3);
    }

    /**
     * @param  list<int>  $clienteIds  vacío si el destino no es una lista manual
     */
    public function enviar(
        string $titulo,
        string $cuerpo,
        string $tipo,
        string $destino,
        array $clienteIds,
        ?int $creadoPor,
        ?int $dias = null,
    ): PushAviso {
        $titulo = trim($titulo);
        $cuerpo = trim($cuerpo);
        $tipo = in_array($tipo, array_keys(PushAviso::tipos()), true) ? $tipo : 'aviso';
        $destino = array_key_exists($destino, PushAviso::destinos()) ? $destino : 'seleccionados';
        $dias = $this->normalizarDias($dias);

        $idsPedidos = $destino === 'seleccionados'
            ? $this->normalizarClienteIds($clienteIds)
            : [];

        $users = $this->usuariosDestino($destino, $idsPedidos, $dias);

        // Un usuario por cliente: el de acceso más reciente.
        $porCliente = $users->unique('cliente_id')->values();
        $conTokenIds = $porCliente->pluck('cliente_id')->map(fn ($id) => (int) $id)->all();

        $omitidos = 0;
        if ($destino === 'seleccionados') {
            $sinToken = array_values(array_diff($idsPedidos, $conTokenIds));
            $omitidos = count($sinToken);
            if ($sinToken !== []) {
                Log::info('Aviso push: clientes seleccionados sin token activo', [
                    'sin_token' => $sinToken,
                    'con_token' => $conTokenIds,
                ]);
            }
        }

        $tipoFcm = $tipo === 'promocion' ? 'premios' : 'aviso';
        $total = $destino === 'seleccionados' ? count($idsPedidos) : $porCliente->count();

        $aviso = PushAviso::query()->create([
            'titulo' => $titulo,
            'cuerpo' => $cuerpo,
            'tipo' => $tipo,
            'destino' => $destino,
            'dias' => $destino === 'por_vencer' ? $dias : null,
            'cliente_ids' => $destino === 'seleccionados' ? $idsPedidos : null,
            'total_destinatarios' => $total,
            'enviados' => 0,
            'fallidos' => 0,
            'omitidos' => $omitidos,
            'creado_por' => $creadoPor,
        ]);

        $cuentas = $this->mapaCuentas($conTokenIds);
        $enviados = 0;
        $fallidos = 0;
        $filas = [];
        $ahora = now();

        if ($destino === 'seleccionados' && isset($sinToken) && $sinToken !== []) {
            foreach ($sinToken as $clienteId) {
                $filas[] = $this->filaEntrega($aviso->id, (int) $clienteId, PushAvisoEntrega::OMITIDO, 'SIN_TOKEN', $ahora);
            }
        }

        foreach ($porCliente as $user) {
            $clienteId = (int) $user->cliente_id;
            $vars = $this->variablesCliente($user, $cuentas[$clienteId] ?? null);
            $tituloCliente = trim(mb_substr(PushAvisoPlantilla::aplicar($titulo, $vars), 0, 180));
            $cuerpoCliente = trim(mb_substr(PushAvisoPlantilla::aplicar($cuerpo, $vars), 0, 900));
            if ($tituloCliente === '') {
                $tituloCliente = 'Aviso';
            }
            if ($cuerpoCliente === '') {
                $cuerpoCliente = $tituloCliente;
            }

            $ok = false;
            try {
                $ok = $this->fcm->notifyUser($user, $tituloCliente, $cuerpoCliente, [
                    'tipo' => $tipoFcm,
                    'tab' => $tipoFcm,
                    'aviso_id' => (string) $aviso->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Aviso push excepción: '.$e->getMessage(), [
                    'aviso_id' => $aviso->id,
                    'cliente_id' => $user->cliente_id,
                ]);
            }

            if ($ok) {
                $enviados++;
                $filas[] = $this->filaEntrega($aviso->id, $clienteId, PushAvisoEntrega::ENVIADO, null, $ahora);
            } else {
                $fallidos++;
                $motivo = $this->fcm->ultimoFallo() ?: 'NO_ENTREGADO';
                $filas[] = $this->filaEntrega($aviso->id, $clienteId, PushAvisoEntrega::FALLIDO, $motivo, $ahora);
            }
        }

        foreach (array_chunk($filas, 100) as $lote) {
            PushAvisoEntrega::query()->insert($lote);
        }

        $aviso->update([
            'enviados' => $enviados,
            'fallidos' => $fallidos,
            'omitidos' => $omitidos,
        ]);

        return $aviso->fresh() ?? $aviso;
    }

    /**
     * Buscar clientes con push para el selector del panel.
     *
     * @return list<array{cliente_id:int,nombre:?string,apellido:?string,cedula:?string}>
     */
    public function buscarConPush(string $q, int $limit = 15): array
    {
        $q = trim($q);
        $idHint = null;
        if (preg_match('/#?\s*(\d+)\s*$/u', $q, $m)) {
            $idHint = (int) $m[1];
        }
        if ($idHint === null && mb_strlen($q) < 2) {
            return [];
        }

        return $this->queryConPush()
            ->where(function ($outer) use ($q, $idHint) {
                $outer->whereHas('cliente', function ($query) use ($q) {
                    $query->buscarTexto($q);
                });
                if ($idHint) {
                    $outer->orWhere('cliente_id', $idHint);
                }
            })
            ->with('cliente:cliente_id,nombre,apellido,cedula')
            ->orderByDesc('ultimo_acceso_at')
            ->limit($limit)
            ->get()
            ->unique('cliente_id')
            ->map(function (User $user) {
                $c = $user->cliente;

                return [
                    'cliente_id' => (int) $user->cliente_id,
                    'nombre' => $c?->nombre,
                    'apellido' => $c?->apellido,
                    'cedula' => $c?->cedula,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Reenvía un aviso existente (crea un nuevo registro en historial).
     * Los grupos se vuelven a calcular con los clientes que cumplen hoy.
     */
    public function reenviar(PushAviso $aviso, ?int $creadoPor): PushAviso
    {
        return $this->enviar(
            (string) $aviso->titulo,
            (string) $aviso->cuerpo,
            (string) $aviso->tipo,
            (string) $aviso->destino,
            is_array($aviso->cliente_ids) ? $aviso->cliente_ids : [],
            $creadoPor,
            $aviso->dias !== null ? (int) $aviso->dias : null
        );
    }

    /**
     * @param  list<int>  $clienteIds
     * @return Collection<int, User>
     */
    private function usuariosDestino(string $destino, array $clienteIds, int $dias): Collection
    {
        $base = $this->queryConPush()
            ->with('cliente:cliente_id,nombre,apellido,cedula')
            ->orderByDesc('ultimo_acceso_at')
            ->orderBy('cliente_id');

        if ($destino === 'todos') {
            return $base->get();
        }

        if ($destino === 'seleccionados') {
            $ids = $this->normalizarClienteIds($clienteIds);
            if ($ids === []) {
                return collect();
            }

            return $base->whereIn('cliente_id', $ids)->get();
        }

        $coinciden = $this->idsQueCoinciden($destino, $this->idsConPush(), $dias);
        if ($coinciden === []) {
            return collect();
        }

        return $base->whereIn('cliente_id', $coinciden)->get();
    }

    /**
     * @param  list<int>  $pushIds
     * @return list<int>
     */
    private function idsQueCoinciden(string $destino, array $pushIds, int $dias): array
    {
        if ($pushIds === []) {
            return [];
        }

        return match ($destino) {
            'pendiente_pago' => array_map('intval', array_keys($this->mapaCuentas($pushIds))),
            'vencidas' => array_map('intval', array_keys(array_filter(
                $this->mapaCuentas($pushIds),
                fn (array $cuenta) => ($cuenta['vencidas'] ?? 0) > 0
            ))),
            'por_vencer' => $this->idsPorVencer($pushIds, $dias),
            'suspendidos' => $this->idsServicio($pushIds, Servicio::ESTADO_SUSPENDIDO),
            'cortados' => $this->idsServicio($pushIds, Servicio::ESTADO_CORTADO),
            default => [],
        };
    }

    /**
     * Saldo, cantidad de facturas y vencimiento más antiguo, solo facturas con saldo.
     *
     * @param  list<int>  $clienteIds
     * @return array<int, array{saldo: float, facturas: int, vencimiento: string, vencidas: int}>
     */
    private function mapaCuentas(array $clienteIds): array
    {
        $clienteIds = $this->normalizarClienteIds($clienteIds);
        if ($clienteIds === []) {
            return [];
        }

        $saldo = FacturaInterna::sqlSaldoPendienteExpr();
        $hoy = now()->toDateString();
        $out = [];

        foreach (array_chunk($clienteIds, 500) as $chunk) {
            $rows = FacturaInterna::query()
                ->whereIn('cliente_id', $chunk)
                ->whereIn('estado', ['pendiente', 'emitida'])
                ->whereRaw($saldo.' > 0.009')
                ->groupBy('cliente_id')
                ->selectRaw(
                    'cliente_id, SUM('.$saldo.') as saldo, COUNT(*) as facturas, MIN(fecha_vencimiento) as vencimiento, SUM(CASE WHEN fecha_vencimiento IS NOT NULL AND fecha_vencimiento < ? THEN 1 ELSE 0 END) as vencidas',
                    [$hoy]
                )
                ->get();

            foreach ($rows as $row) {
                $vencTxt = '';
                if (! empty($row->vencimiento)) {
                    try {
                        $vencTxt = Carbon::parse($row->vencimiento)->format('d/m/Y');
                    } catch (\Throwable) {
                        $vencTxt = '';
                    }
                }

                $out[(int) $row->cliente_id] = [
                    'saldo' => (float) $row->saldo,
                    'facturas' => (int) $row->facturas,
                    'vencimiento' => $vencTxt,
                    'vencidas' => (int) $row->vencidas,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $pushIds
     * @return list<int>
     */
    private function idsPorVencer(array $pushIds, int $dias): array
    {
        $pushIds = $this->normalizarClienteIds($pushIds);
        if ($pushIds === []) {
            return [];
        }

        $saldo = FacturaInterna::sqlSaldoPendienteExpr();
        $desde = now()->startOfDay()->toDateString();
        $hasta = now()->startOfDay()->addDays($dias)->toDateString();
        $ids = [];

        foreach (array_chunk($pushIds, 500) as $chunk) {
            $found = FacturaInterna::query()
                ->whereIn('cliente_id', $chunk)
                ->whereIn('estado', ['pendiente', 'emitida'])
                ->whereDate('fecha_vencimiento', '>=', $desde)
                ->whereDate('fecha_vencimiento', '<=', $hasta)
                ->whereRaw($saldo.' > 0.009')
                ->distinct()
                ->pluck('cliente_id');

            foreach ($found as $id) {
                $ids[(int) $id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param  list<int>  $pushIds
     * @return list<int>
     */
    private function idsServicio(array $pushIds, string $estado): array
    {
        $pushIds = $this->normalizarClienteIds($pushIds);
        if ($pushIds === []) {
            return [];
        }

        $ids = [];
        foreach (array_chunk($pushIds, 500) as $chunk) {
            $found = Servicio::query()
                ->whereIn('cliente_id', $chunk)
                ->where('estado', $estado)
                ->distinct()
                ->pluck('cliente_id');

            foreach ($found as $id) {
                $ids[(int) $id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param  array{saldo: float, facturas: int, vencimiento: string, vencidas: int}|null  $cuenta
     * @return array<string, string>
     */
    private function variablesCliente(User $user, ?array $cuenta): array
    {
        $cliente = $user->cliente;
        $nombre = trim((string) ($cliente?->nombre ?? ''));
        $apellido = trim((string) ($cliente?->apellido ?? ''));
        $completo = trim($nombre.' '.$apellido);
        $saldo = (float) ($cuenta['saldo'] ?? 0);
        $vencimiento = trim((string) ($cuenta['vencimiento'] ?? ''));

        return [
            'nombre' => $nombre !== '' ? $nombre : 'cliente',
            'apellido' => $apellido,
            'nombre_completo' => $completo !== '' ? $completo : 'cliente',
            'saldo_pend' => number_format((int) round($saldo), 0, ',', '.'),
            'facturas' => (string) (int) ($cuenta['facturas'] ?? 0),
            'vencimiento' => $vencimiento !== '' ? $vencimiento : 'sin fecha',
            'cedula' => trim((string) ($cliente?->cedula ?? '')),
        ];
    }

    /**
     * @return array{push_aviso_id: int, cliente_id: int, estado: string, motivo: ?string, created_at: \Illuminate\Support\Carbon, updated_at: \Illuminate\Support\Carbon}
     */
    private function filaEntrega(int $avisoId, int $clienteId, string $estado, ?string $motivo, \Illuminate\Support\Carbon $ahora): array
    {
        return [
            'push_aviso_id' => $avisoId,
            'cliente_id' => $clienteId,
            'estado' => $estado,
            'motivo' => $motivo !== null ? mb_substr($motivo, 0, 180) : null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
    }

    /**
     * @param  list<mixed>  $clienteIds
     * @return list<int>
     */
    private function normalizarClienteIds(array $clienteIds): array
    {
        return collect($clienteIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizarDias(?int $dias): int
    {
        $n = $dias ?? ClientePushNotifier::diasAntesVencimiento();

        return max(1, min(30, (int) $n));
    }

    /**
     * @return list<int>
     */
    private function idsConPush(): array
    {
        return $this->queryConPush()
            ->select('cliente_id')
            ->distinct()
            ->pluck('cliente_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
    }

    private function queryConPush()
    {
        return User::query()
            ->whereNotNull('cliente_id')
            ->activos()
            ->whereNotNull('push_token')
            ->where('push_token', '!=', '');
    }
}
