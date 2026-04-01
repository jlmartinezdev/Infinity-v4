<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Services\WispHubApiService;
use Illuminate\Console\Command;

class ImportarWispHubClientesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wisphub:importar-clientes
                            {--limit= : Máximo de registros a traer por página (default 50)}
                            {--max= : Máximo total de clientes a importar (sin límite si no se indica)}
                            {--estado= : Filtrar por estado WispHub: 1=Activo, 2=Suspendido, 3=Cancelado, 4=Gratis}
                            {--dry-run : Solo simular, no guardar en BD}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa clientes desde la API de WispHub (https://wisphub.net/api-docs)';

    public function handle(WispHubApiService $wisphub): int
    {
        if (! $wisphub->isConfigured()) {
            $this->error('WispHub no está configurado. Añade WISPHUB_API_KEY y opcionalmente WISPHUB_BASE_URL a .env');
            $this->line('  WISPHUB_API_KEY=tu_clave_api');
            $this->line('  WISPHUB_BASE_URL=https://api.wisphub.net  (o https://sandbox-api.wisphub.net para pruebas)');
            return self::FAILURE;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT) ?: 50;
        $maxTotal = $this->option('max') ? filter_var($this->option('max'), FILTER_VALIDATE_INT) : null;
        $estado = $this->option('estado') ? filter_var($this->option('estado'), FILTER_VALIDATE_INT) : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se guardarán cambios.');
        }

        $params = ['limit' => $limit];
        if ($estado !== null) {
            $params['estado'] = $estado;
        }

        $offset = 0;
        $importados = 0;
        $actualizados = 0;
        $errores = 0;

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current% registros - nuevos: %importados% | actualizados: %actualizados%');
        $bar->setMessage('0', 'importados');
        $bar->setMessage('0', 'actualizados');
        $bar->start();

        while (true) {
            $params['offset'] = $offset;
            $data = $wisphub->getClientes($params);

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
                        $this->newLine();
                        $this->error("Error en id_servicio " . ($item['id_servicio'] ?? '?') . ': ' . $e->getMessage());
                    }
                } else {
                    $existe = Cliente::where('cedula', $cedula)->exists();
                    if ($existe) {
                        $actualizados++;
                    } else {
                        $importados++;
                    }
                }

                $bar->setMessage((string) $importados, 'importados');
                $bar->setMessage((string) $actualizados, 'actualizados');
                $bar->advance();
            }

            $offset += $limit;
            if (count($results) < $limit) {
                break;
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Listo. Nuevos: {$importados}, Actualizados: {$actualizados}" . ($errores > 0 ? ", Errores: {$errores}" : ''));

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function normalizarCedula(mixed $valor): string
    {
        return trim((string) $valor);
    }

    /**
     * Mapea estado de WispHub a estado en Infinity (cliente).
     * WispHub: 1=Activo, 2=Suspendido, 3=Cancelado, 4=Gratis (puede venir como string o int).
     */
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
            4 => 'activo', // Gratis -> activo
            default => null,
        };
    }
}
