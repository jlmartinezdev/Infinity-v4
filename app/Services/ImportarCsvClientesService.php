<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\PoolIpAsignada;
use App\Models\Servicio;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportarCsvClientesService
{
    /**
     * Mapeo de prefijo IP a pool_id. Si no coincide ninguno, se usa pool_id 6.
     */
    protected array $ipPoolMap = [
        '10.0' => 2,
        '10.1' => 8,
        '10.2' => 3,
        '10.3' => 7,
        '10.5' => 4,
        '10.6' => 5,
    ];

    protected int $poolIdDefault = 6;

    /**
     * Procesa el CSV e importa clientes y servicios.
     *
     * @return array{creados: int, actualizados: int, servicios_creados: int, errores: int, mensajes: array<string>}
     */
    public function procesar(UploadedFile $file, bool $dryRun = false): array
    {
        $creados = 0;
        $actualizados = 0;
        $serviciosCreados = 0;
        $errores = 0;
        $mensajes = [];

        $lineas = $this->leerCsv($file);
        if (empty($lineas)) {
            return [
                'creados' => 0,
                'actualizados' => 0,
                'servicios_creados' => 0,
                'errores' => 1,
                'mensajes' => ['El archivo CSV está vacío o no tiene el formato esperado.'],
            ];
        }

        $headers = array_map(function ($h) {
            $h = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)));
            return str_replace(' ', '_', $h);
        }, $lineas[0]);
        $expected = ['cedula', 'estado', 'estado_pago', 'fecha_instalacion', 'ip', 'nombre', 'password_pppoe', 'plan_id', 'router_id', 'usuario_pppoe'];
        $missing = array_diff($expected, $headers);
        if (! empty($missing)) {
            return [
                'creados' => 0,
                'actualizados' => 0,
                'servicios_creados' => 0,
                'errores' => 1,
                'mensajes' => ['Columnas requeridas faltantes: ' . implode(', ', $missing)],
            ];
        }

        $filas = array_slice($lineas, 1);

        foreach ($filas as $num => $fila) {
            $numLinea = $num + 2;
            $row = array_combine($headers, array_pad($fila, count($headers), ''));

            $cedula = trim((string) ($row['cedula'] ?? ''));
            $nombre = trim((string) ($row['nombre'] ?? ''));

            if ($cedula === '' || $nombre === '') {
                $errores++;
                $mensajes[] = "Línea {$numLinea}: cedula y nombre son obligatorios.";
                continue;
            }

            try {
                DB::beginTransaction();

                $cliente = Cliente::where('cedula', $cedula)->first();
                if (! $cliente) {
                    if (! $dryRun) {
                        $cliente = Cliente::create([
                            'cedula' => $cedula,
                            'nombre' => $nombre,
                            'apellido' => '',
                            'estado' => 'activo',
                        ]);
                    } else {
                        $cliente = new Cliente(['cedula' => $cedula, 'nombre' => $nombre, 'cliente_id' => 0]);
                    }
                    $creados++;
                } else {
                    if (! $dryRun) {
                        $cliente->update(['nombre' => $nombre, 'estado' => 'activo']);
                    }
                    $actualizados++;
                }

                $ip = trim((string) ($row['ip'] ?? ''));
                $poolId = $this->obtenerPoolIdDesdeIp($ip);
                if (! $poolId) {
                    DB::rollBack();
                    $errores++;
                    $mensajes[] = "Línea {$numLinea}: IP vacía (se requiere IP para asignar pool).";
                    continue;
                }
                if ($ip && str_ends_with($ip, '.255')) {
                    DB::rollBack();
                    $errores++;
                    $mensajes[] = "Línea {$numLinea}: La IP no puede terminar en .255 (reservada para broadcast).";
                    continue;
                }

                $planId = (int) ($row['plan_id'] ?? 0);
                if ($planId < 1 || ! \App\Models\Plan::where('plan_id', $planId)->exists()) {
                    DB::rollBack();
                    $errores++;
                    $mensajes[] = "Línea {$numLinea}: plan_id inválido o no existe: {$planId}";
                    continue;
                }

                $usuarioPppoe = trim((string) ($row['usuario_pppoe'] ?? ''));
                $existeServicio = $cliente->cliente_id
                    ? Servicio::where('cliente_id', $cliente->cliente_id)->where('usuario_pppoe', $usuarioPppoe)->exists()
                    : false;

                if (! $existeServicio && $cliente->cliente_id && ! $dryRun) {
                    $fechaInstalacion = $this->parsearFecha($row['fecha_instalacion'] ?? '');

                    Servicio::create([
                        'cliente_id' => $cliente->cliente_id,
                        'pool_id' => $poolId,
                        'plan_id' => $planId,
                        'ip' => $ip ?: null,
                        'usuario_pppoe' => $usuarioPppoe ?: null,
                        'password_pppoe' => trim((string) ($row['password_pppoe'] ?? '')) ?: null,
                        'fecha_instalacion' => $fechaInstalacion,
                        'estado_pago' => trim((string) ($row['estado_pago'] ?? '')) ?: null,
                        'estado' => Servicio::ESTADO_ACTIVO,
                    ]);
                    $serviciosCreados++;

                    if ($ip) {
                        PoolIpAsignada::updateOrCreate(
                            ['ip' => $ip, 'pool_id' => $poolId],
                            ['estado' => 'asignada']
                        );
                    }
                } elseif (! $existeServicio) {
                    $serviciosCreados++;
                }

                if ($dryRun) {
                    DB::rollBack();
                } else {
                    DB::commit();
                }
            } catch (\Throwable $e) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                $errores++;
                $mensajes[] = "Línea {$numLinea}: " . $e->getMessage();
                Log::error('ImportarCsvClientes error', ['linea' => $numLinea, 'row' => $row ?? [], 'exception' => $e]);
            }
        }

        return [
            'creados' => $creados,
            'actualizados' => $actualizados,
            'servicios_creados' => $serviciosCreados,
            'errores' => $errores,
            'mensajes' => array_slice($mensajes, -20),
        ];
    }

    protected function obtenerPoolIdDesdeIp(string $ip): ?int
    {
        if ($ip === '') {
            return null;
        }
        foreach ($this->ipPoolMap as $prefijo => $poolId) {
            if (str_starts_with($ip, $prefijo . '.')) {
                return $poolId;
            }
        }
        return $this->poolIdDefault;
    }

    protected function parsearFecha(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        try {
            // Formatos con hora (ej: 7/3/2024 12:07) - H:i ANTES de H:i:s para no exigir segundos
            $formats = [
                'j/n/Y H:i',
                'j/n/Y H:i:s',
                'd/m/Y H:i',
                'd/m/Y H:i:s',
                'm/d/Y H:i',
                'm/d/Y H:i:s',
                'Y-m-d H:i',
                'Y-m-d H:i:s',
                'j-n-Y H:i',
                'j-n-Y H:i:s',
                'd-m-Y H:i',
                'd-m-Y H:i:s',
                'Y/m/d H:i',
                'Y/m/d H:i:s',
                'd/m/Y',
                'j/n/Y',
                'd-m-Y',
                'Y/m/d',
                'Y-m-d',
            ];
            foreach ($formats as $fmt) {
                $d = @Carbon::createFromFormat($fmt, $valor);
                if ($d instanceof \DateTimeInterface) {
                    return $d->format('Y-m-d');
                }
            }
            $d = Carbon::parse($valor);
            return $d->format('Y-m-d');
        } catch (\Throwable $e) {
            Log::warning('ImportarCsvClientes: fecha no parseada', ['valor' => $valor, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function leerCsv(UploadedFile $file): array
    {
        $lineas = [];
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            return [];
        }
        while (($fila = fgetcsv($handle, 0, ';')) !== false) {
            if (count($fila) === 1 && str_contains($fila[0], ',')) {
                $fila = str_getcsv($fila[0], ',');
            }
            $lineas[] = $fila;
        }
        fclose($handle);
        return $lineas;
    }
}
