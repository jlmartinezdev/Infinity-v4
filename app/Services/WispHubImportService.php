<?php

namespace App\Services;

use App\Models\Cliente;

class WispHubImportService
{
    public function __construct(
        protected WispHubApiService $wisphub
    ) {}

    /**
     * Ejecuta la importación de clientes desde WispHub.
     *
     * @param  array{limit?: int, max?: int, estado?: int, dry_run?: bool}  $options
     * @return array{importados: int, actualizados: int, errores: int, configured: bool, message?: string}
     */
    public function run(array $options = []): array
    {
        if (! $this->wisphub->isConfigured()) {
            return [
                'importados' => 0,
                'actualizados' => 0,
                'errores' => 0,
                'configured' => false,
                'message' => 'WispHub no está configurado. Configure WISPHUB_API_KEY en .env',
            ];
        }

        $limit = isset($options['limit']) && (int) $options['limit'] > 0 ? (int) $options['limit'] : 50;
        $maxTotal = isset($options['max']) && (int) $options['max'] > 0 ? (int) $options['max'] : null;
        $estado = isset($options['estado']) ? (int) $options['estado'] : null;
        $dryRun = ! empty($options['dry_run']);

        $params = ['limit' => $limit];
        if ($estado !== null && $estado !== '') {
            $params['estado'] = $estado;
        }

        $offset = 0;
        $importados = 0;
        $actualizados = 0;
        $errores = 0;

        while (true) {
            $params['offset'] = $offset;
            $data = $this->wisphub->getClientes($params);
            $results = $data['results'] ?? [];

            if (empty($results)) {
                break;
            }

            foreach ($results as $item) {
                if ($maxTotal !== null && ($importados + $actualizados) >= $maxTotal) {
                    break 2;
                }

                $cedula = $this->normalizarCedula($item['cedula'] ?? $item['id_servicio'] ?? '');
                $nombre = trim($item['nombre'] ?? '');
                $apellido = trim($item['apellidos'] ?? $item['apellido'] ?? '');
                $email = trim($item['email'] ?? '');
                $telefono = trim($item['telefono'] ?? '');
                $direccion = trim($item['direccion'] ?? '');

                if ($cedula === '') {
                    $cedula = 'wisphub_' . ($item['id_servicio'] ?? uniqid());
                }

                $estadoCliente = $this->mapearEstadoWispHub($item['estado'] ?? null);

                if (! $dryRun) {
                    try {
                        $cliente = Cliente::where('cedula', $cedula)->first();
                        if ($cliente) {
                            $cliente->update([
                                'nombre' => $nombre ?: $cliente->nombre,
                                'apellido' => $apellido ?: $cliente->apellido,
                                'email' => $email ?: $cliente->email,
                                'telefono' => $telefono ?: $cliente->telefono,
                                'direccion' => $direccion ?: $cliente->direccion,
                                'estado' => $estadoCliente ?? $cliente->estado,
                            ]);
                            $actualizados++;
                        } else {
                            Cliente::create([
                                'cedula' => $cedula,
                                'nombre' => $nombre ?: 'Sin nombre',
                                'apellido' => $apellido,
                                'email' => $email ?: null,
                                'telefono' => $telefono ?: null,
                                'direccion' => $direccion ?: null,
                                'estado' => $estadoCliente ?? 'activo',
                            ]);
                            $importados++;
                        }
                    } catch (\Throwable $e) {
                        $errores++;
                    }
                } else {
                    if (Cliente::where('cedula', $cedula)->exists()) {
                        $actualizados++;
                    } else {
                        $importados++;
                    }
                }
            }

            $offset += $limit;
            if (count($results) < $limit) {
                break;
            }
        }

        return [
            'importados' => $importados,
            'actualizados' => $actualizados,
            'errores' => $errores,
            'configured' => true,
        ];
    }

    private function normalizarCedula(mixed $valor): string
    {
        return trim((string) $valor);
    }

    private function mapearEstadoWispHub(mixed $estado): ?string
    {
        if ($estado === null) {
            return null;
        }
        $n = is_numeric($estado) ? (int) $estado : null;
        return match ($n) {
            1 => 'activo',
            2 => 'suspendido',
            3 => 'cancelado',
            4 => 'activo',
            default => null,
        };
    }
}
