<?php

namespace App\Console\Commands;

use App\Models\WaCaso;
use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\Soporte\EstiloInterplus;
use App\Services\WhatsApp\Soporte\FraseCliente;
use Illuminate\Console\Command;

class WhatsAppExtraerEstiloCasosCommand extends Command
{
    protected $signature = 'whatsapp:extraer-estilo-casos
                            {--limite=4000 : Máximo de salidas a leer}
                            {--min=3 : Repeticiones mínimas para crear un caso}
                            {--dry-run : Solo muestra, no guarda}';

    protected $description = 'Crea casos Interplus desde respuestas humanas de WhatsApp, sin cuentas ni voseo';

    public function handle(): int
    {
        $limite = max(100, (int) $this->option('limite'));
        $min = max(2, (int) $this->option('min'));
        $dry = (bool) $this->option('dry-run');

        $filas = WhatsappMensaje::query()
            ->where('direccion', WhatsappMensaje::DIRECCION_SALIDA)
            ->whereIn('contexto_tipo', ['manual_panel', 'app_whatsapp'])
            ->where('telefono', 'not like', '595000%')
            ->whereNotNull('cuerpo')
            ->where('cuerpo', '!=', '')
            ->orderByDesc('id')
            ->limit($limite)
            ->get(['cuerpo', 'contexto_tipo']);

        $conteo = [];
        foreach ($filas as $fila) {
            $texto = $this->normalizar((string) $fila->cuerpo);
            if ($texto === null || EstiloInterplus::textoProhibido($texto)) {
                continue;
            }
            $clave = mb_strtolower($texto);
            if (! isset($conteo[$clave])) {
                $conteo[$clave] = ['texto' => $texto, 'n' => 0];
            }
            $conteo[$clave]['n']++;
        }

        uasort($conteo, fn ($a, $b) => $b['n'] <=> $a['n']);
        $candidatos = array_values(array_filter($conteo, fn ($c) => $c['n'] >= $min));
        $this->info('Salidas leídas: '.$filas->count().' · frases repetidas ≥'.$min.': '.count($candidatos));

        $creados = 0;
        $actualizados = 0;
        foreach (array_slice($candidatos, 0, 80) as $item) {
            $etiqueta = $this->etiqueta($item['texto']);
            $titulo = mb_substr($item['texto'], 0, 60);
            $palabras = $this->palabras($item['texto'], $etiqueta);
            if ($dry) {
                $this->line("[{$etiqueta}] ({$item['n']}) {$item['texto']}");
                continue;
            }
            $existente = WaCaso::query()
                ->where('etiqueta', $etiqueta)
                ->where('texto', $item['texto'])
                ->first();
            if ($existente) {
                $existente->update([
                    'titulo' => $titulo,
                    'palabras' => $palabras,
                    'activo' => true,
                ]);
                $actualizados++;
            } else {
                WaCaso::query()->create([
                    'titulo' => $titulo,
                    'etiqueta' => $etiqueta,
                    'palabras' => $palabras,
                    'texto' => $item['texto'],
                    'activo' => true,
                ]);
                $creados++;
            }
        }

        $this->table(['metric', 'valor'], [
            ['creados', (string) $creados],
            ['actualizados', (string) $actualizados],
            ['estilo', 'sin cambios'],
            ['wa_casos', (string) WaCaso::query()->count()],
        ]);

        return self::SUCCESS;
    }

    private function normalizar(string $cuerpo): ?string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $cuerpo) ?? '');
        $t = preg_replace('/\+?595[\s.\-]*9\d{2}[\s.\-]*\d{3}[\s.\-]*\d{3}/u', '', $t) ?? $t;
        $t = preg_replace('/\b\d{1,3}(?:\.\d{1,3}){3}\b/', '', $t) ?? $t;
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? '');
        if ($t === '' || mb_strlen($t) < 12 || mb_strlen($t) > 280) {
            return null;
        }
        if (FraseCliente::filtrar($t, '') === '') {
            return null;
        }
        if (preg_match('/https?:\/\//i', $t)) {
            return null;
        }

        return $t;
    }

    private function etiqueta(string $texto): string
    {
        $n = mb_strtolower($texto);
        if (preg_match('/suspend|saldo|pago|comprobante|transfer/u', $n)) {
            return 'cobranza';
        }
        if (preg_match('/cobertura|ubicaci|mapa|instal/u', $n)) {
            return 'cobertura';
        }
        if (preg_match('/plac[eé]r atender|a las [oó]rdenes|quedamos a/u', $n)) {
            return 'cierre';
        }
        if (preg_match('/hola|buen[oa]s|bienvenid/u', $n)) {
            return 'saludo';
        }
        if (preg_match('/wifi|router|cable|se[nñ]al|internet|conexi/u', $n)) {
            return 'soporte';
        }

        return 'general';
    }

    private function palabras(string $texto, string $etiqueta): string
    {
        $base = match ($etiqueta) {
            'cobranza' => 'pago,saldo,suspendido,comprobante',
            'cobertura' => 'cobertura,ubicacion,mapa,instalar',
            'cierre' => 'gracias,listo,perfecto,resuelto',
            'saludo' => 'hola,buenas,buen dia',
            'soporte' => 'wifi,internet,no anda,lento,router',
            default => '',
        };
        $tokens = preg_split('/\s+/u', mb_strtolower($texto)) ?: [];
        $extra = [];
        foreach ($tokens as $tok) {
            $tok = trim($tok, '.,;:¡!¿?"\'');
            if (mb_strlen($tok) >= 5 && count($extra) < 4) {
                $extra[] = $tok;
            }
        }

        return trim($base.','.implode(',', $extra), ',');
    }
}
