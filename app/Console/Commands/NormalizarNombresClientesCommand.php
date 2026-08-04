<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Support\ClienteNombreNormalizer;
use Illuminate\Console\Command;

class NormalizarNombresClientesCommand extends Command
{
    protected $signature = 'clientes:normalizar-nombres
                            {--dry-run : Mostrar cambios sin guardar}
                            {--cliente_id= : Procesar un solo cliente}
                            {--limit= : Cantidad máxima a procesar}';

    protected $description = 'Normaliza nombres con guion bajo a MAYÚSCULAS (omite registros que terminan en _2).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $clienteId = $this->option('cliente_id') ? (int) $this->option('cliente_id') : null;
        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : null;

        if ($dryRun) {
            $this->warn('Modo dry-run: no se guardarán cambios.');
        }

        $query = Cliente::query()
            ->where(function ($q) {
                $q->whereRaw("LOCATE('_', nombre) > 0")
                    ->orWhereRaw("LOCATE('_', COALESCE(apellido, '')) > 0");
            })
            ->orderBy('cliente_id');

        if ($clienteId !== null) {
            $query->where('cliente_id', $clienteId);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $clientes = $query->get();

        if ($clientes->isEmpty()) {
            $this->info('No hay clientes con guion bajo en nombre o apellido.');

            return self::SUCCESS;
        }

        $filas = [];
        $actualizados = 0;
        $sinCambio = 0;
        $omitidos = 0;

        foreach ($clientes as $cliente) {
            $resultado = ClienteNombreNormalizer::normalizar($cliente->nombre, $cliente->apellido);

            if ($resultado['omitir']) {
                $omitidos++;
                continue;
            }

            if (! $resultado['cambio']) {
                $sinCambio++;
                continue;
            }

            $antes = trim($cliente->nombre.' '.($cliente->apellido ?? ''));
            $despues = trim($resultado['nombre'].' '.($resultado['apellido'] ?? ''));

            $filas[] = [
                $cliente->cliente_id,
                $cliente->cedula ?: '—',
                $antes,
                $despues,
            ];

            if (! $dryRun) {
                $cliente->update([
                    'nombre' => $resultado['nombre'] !== '' ? $resultado['nombre'] : $cliente->nombre,
                    'apellido' => $resultado['apellido'],
                ]);
            }

            $actualizados++;
        }

        if ($filas !== []) {
            $this->table(
                ['ID', 'Cédula', 'Antes', 'Después'],
                $filas
            );
        }

        $this->newLine();
        $this->info('Candidatos con _: '.$clientes->count());
        $this->info(($dryRun ? 'A normalizar' : 'Normalizados').": {$actualizados}");

        if ($omitidos > 0) {
            $this->line("Omitidos (terminan en _2): {$omitidos}");
        }

        if ($sinCambio > 0) {
            $this->line("Sin cambio tras evaluar: {$sinCambio}");
        }

        if ($dryRun && $actualizados > 0) {
            $this->comment('Ejecute sin --dry-run para aplicar los cambios.');
        }

        return self::SUCCESS;
    }
}
