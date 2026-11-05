<?php

namespace App\Console\Commands;

use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\Soporte\SoporteOrquestador;
use App\Services\WhatsApp\Soporte\SoporteTexto;
use App\Services\WhatsApp\WhatsAppAgentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Audita mensajes reales (meses) contra la clasificación Laravel, sin enviar a Meta.
 */
class WhatsAppAuditarCorpusCommand extends Command
{
    protected $signature = 'whatsapp:auditar-corpus
                            {--meses=5 : Meses hacia atrás}
                            {--muestra=800 : Máximo de entradas a clasificar}
                            {--hilos=25 : Hilos distintos a rellenar}
                            {--out= : Ruta JSON de salida}';

    protected $description = 'Clasifica chats reales de varios meses y señala dónde se rompe la intención';

    public function handle(SoporteOrquestador $orquestador): int
    {
        $meses = max(1, (int) $this->option('meses'));
        $muestra = max(50, (int) $this->option('muestra'));
        $hilosN = max(5, (int) $this->option('hilos'));
        $desde = now()->subMonths($meses)->startOfDay();
        $out = (string) ($this->option('out') ?: storage_path('app/wa-corpus-audit-'.now()->format('Ymd-His').'.json'));

        $base = WhatsappMensaje::query()
            ->where('direccion', WhatsappMensaje::DIRECCION_ENTRADA)
            ->where('created_at', '>=', $desde)
            ->where('telefono', 'not like', WhatsappMensaje::TELEFONO_SANDBOX_PREFIX.'%')
            ->whereNotNull('cuerpo')
            ->where('cuerpo', '!=', '');

        $total = (clone $base)->count();
        $min = (clone $base)->min('created_at');
        $max = (clone $base)->max('created_at');

        // Muestra estratificada: recientes + aleatoria del período.
        $idsRecientes = (clone $base)->orderByDesc('id')->limit((int) floor($muestra * 0.4))->pluck('id');
        $idsAzar = (clone $base)->inRandomOrder()->limit((int) ceil($muestra * 0.6))->pluck('id');
        $ids = $idsRecientes->merge($idsAzar)->unique()->values();

        $mensajes = WhatsappMensaje::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'telefono', 'cuerpo', 'created_at', 'tipo']);

        $conteo = [];
        $riesgos = [];
        $frases = [];

        foreach ($mensajes as $m) {
            $texto = trim((string) $m->cuerpo);
            if ($texto === '' || mb_strlen($texto) > 500) {
                continue;
            }
            $flags = $this->flags($texto);
            $ruta = $this->ruta($flags);
            $conteo[$ruta] = ($conteo[$ruta] ?? 0) + 1;

            $riesgo = $this->riesgo($texto, $flags, $ruta);
            if ($riesgo !== null) {
                $riesgos[] = [
                    'id' => (int) $m->id,
                    'fecha' => (string) $m->created_at,
                    'telefono' => substr((string) $m->telefono, 0, 6).'***',
                    'texto' => mb_substr($texto, 0, 220),
                    'ruta' => $ruta,
                    'riesgo' => $riesgo,
                    'flags' => array_keys(array_filter($flags)),
                ];
            }

            $clave = mb_strtolower(preg_replace('/\s+/', ' ', $texto) ?? $texto);
            if (! isset($frases[$clave]) && count($frases) < 200) {
                $frases[$clave] = [
                    'texto' => mb_substr($texto, 0, 180),
                    'ruta' => $ruta,
                ];
            }
        }

        // Hilos: últimos mensajes por teléfono con actividad de pago/falla.
        $telefonos = (clone $base)
            ->where(function ($q) {
                $q->where('cuerpo', 'like', '%debo%')
                    ->orWhere('cuerpo', 'like', '%abonar%')
                    ->orWhere('cuerpo', 'like', '%cuenta%')
                    ->orWhere('cuerpo', 'like', '%internet%')
                    ->orWhere('cuerpo', 'like', '%wifi%')
                    ->orWhere('cuerpo', 'like', '%cedula%')
                    ->orWhere('cuerpo', 'like', '%cédula%');
            })
            ->orderByDesc('id')
            ->limit(400)
            ->pluck('telefono')
            ->unique()
            ->take($hilosN)
            ->values();

        $hilos = [];
        foreach ($telefonos as $tel) {
            $pasos = WhatsappMensaje::query()
                ->where('telefono', $tel)
                ->where('created_at', '>=', $desde)
                ->orderBy('id')
                ->limit(40)
                ->get(['direccion', 'cuerpo', 'created_at', 'contexto_tipo']);
            if ($pasos->count() < 3) {
                continue;
            }
            $replay = [];
            foreach ($pasos->where('direccion', WhatsappMensaje::DIRECCION_ENTRADA) as $paso) {
                $t = trim((string) $paso->cuerpo);
                if ($t === '') {
                    continue;
                }
                $f = $this->flags($t);
                $replay[] = [
                    'cuando' => (string) $paso->created_at,
                    'texto' => mb_substr($t, 0, 160),
                    'ruta' => $this->ruta($f),
                ];
            }
            $hilos[] = [
                'telefono' => substr((string) $tel, 0, 6).'***',
                'mensajes' => count($pasos),
                'entradas' => $replay,
            ];
        }

        arsort($conteo);
        usort($riesgos, fn ($a, $b) => strcmp($a['riesgo'], $b['riesgo']));

        $reporte = [
            'generado_at' => now()->toDateTimeString(),
            'desde' => $desde->toDateTimeString(),
            'min_msg' => $min,
            'max_msg' => $max,
            'total_entradas_periodo' => $total,
            'clasificados' => $mensajes->count(),
            'rutas' => $conteo,
            'riesgos_count' => count($riesgos),
            'riesgos' => array_slice($riesgos, 0, 120),
            'frases_muestra' => array_values($frases),
            'hilos' => $hilos,
        ];

        File::ensureDirectoryExists(dirname($out));
        File::put($out, json_encode($reporte, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->info("Periodo desde {$desde->toDateString()} — total entradas: {$total}");
        $this->info("Clasificados: {$mensajes->count()} — riesgos: ".count($riesgos));
        $this->table(['ruta', 'cantidad'], collect($conteo)->map(fn ($n, $r) => [$r, $n])->values()->all());
        $this->warn('Top riesgos:');
        foreach (array_slice($riesgos, 0, 25) as $r) {
            $this->line("- [{$r['riesgo']}] {$r['texto']}");
        }
        $this->info("JSON: {$out}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, bool>
     */
    private function flags(string $texto): array
    {
        return [
            'transferencia' => WhatsAppAgentService::textoPareceDatosTransferencia($texto),
            'cuanto_abonar' => SoporteTexto::pareceCuantoAbonar($texto),
            'insiste_saldo' => SoporteTexto::pareceInsisteSaldo($texto),
            'falla' => SoporteTexto::pareceFalla($texto),
            'equipos' => SoporteTexto::pareceConsultaEquipos($texto),
            'ping' => SoporteTexto::pareceConsultaPing($texto),
            'wifi_cambio' => SoporteTexto::pareceCambioWifi($texto),
            'wifi_nombre' => SoporteTexto::pareceConsultaNombreWifi($texto),
            'clave_wifi' => SoporteTexto::parecePideClaveWifiActual($texto),
            'estado_servicio' => SoporteTexto::pareceEstadoServicio($texto),
            'consulta_servicio' => SoporteTexto::pareceConsultaServicio($texto),
            'catalogo' => SoporteTexto::pareceConsultaCatalogo($texto),
            'beneficios' => WhatsAppAgentService::textoPareceBeneficiosPlanes($texto),
            'condiciones' => WhatsAppAgentService::textoPareceCondicionesServicio($texto),
            'plazo' => WhatsAppAgentService::textoParecePlazoPago($texto),
            'titular' => SoporteTexto::pareceConsultaTitular($texto),
            'identificador' => SoporteTexto::esSoloIdentificador($texto),
            'cedula' => SoporteTexto::cedulaEnTexto($texto) !== null,
            'telefono' => SoporteTexto::telefonoEnTexto($texto) !== null,
            'cierre' => SoporteTexto::pareceCierre($texto),
            'ya_funciona' => SoporteTexto::pareceYaFunciona($texto),
            'tv' => SoporteTexto::pareceTelevision($texto),
            'luz' => SoporteTexto::pareceLuz($texto),
            'impresora' => SoporteTexto::pareceImpresora($texto),
            'zona' => SoporteTexto::pareceSeguimientoZona($texto),
            'instalacion' => WhatsAppAgentService::textoPareceInstalacion($texto),
            'cobertura' => WhatsAppAgentService::textoPareceConsultaCobertura($texto),
            'baja' => WhatsAppAgentService::textoPareceBajaServicio($texto),
            'cambio_plan' => WhatsAppAgentService::textoPareceCambioPlan($texto),
            'comprobante' => WhatsAppAgentService::textoParecePago($texto)
                && ! WhatsAppAgentService::textoPareceDatosTransferencia($texto),
        ];
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function ruta(array $flags): string
    {
        foreach ([
            'identificador', 'insiste_saldo', 'transferencia', 'cuanto_abonar', 'plazo',
            'comprobante', 'falla', 'equipos', 'ping', 'wifi_cambio', 'wifi_nombre', 'clave_wifi',
            'estado_servicio', 'consulta_servicio', 'catalogo', 'beneficios', 'condiciones',
            'titular', 'tv', 'luz', 'impresora', 'zona', 'instalacion', 'cobertura', 'baja',
            'cambio_plan', 'ya_funciona', 'cierre',
        ] as $k) {
            if (! empty($flags[$k])) {
                return $k;
            }
        }

        return 'n8n_u_otro';
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function riesgo(string $texto, array $flags, string $ruta): ?string
    {
        $t = mb_strtolower($texto);
        if ($ruta === 'n8n_u_otro') {
            if (preg_match('/debo|abonar|saldo|cuenta|deposit|transf|cedula|c[eé]dula|wifi|internet|ping|plan|debe/u', $t)) {
                return 'posible_intencion_sin_matcher';
            }
            if (preg_match('/^\d{5,8}$|^\d{1,3}(?:\.\d{3})+$/u', trim($texto))) {
                return 'cedula_sin_detectar';
            }
        }
        if (! empty($flags['cuanto_abonar']) && ! empty($flags['falla'])) {
            return 'saldo_y_falla_juntos';
        }
        if (preg_match('/verificando|a la brevedad|seguimos consultando|no logramos/u', $t)) {
            return 'frase_prohibida_en_entrada';
        }
        if ($ruta === 'catalogo' && preg_match('/hablamos|debo|abonar|saldo/u', $t)) {
            return 'catalogo_sospechoso';
        }
        if (preg_match('/cuantos? debe|cuanto es su cuenta|a q cuenta|mi mama|2\.\d{3}\.\d{3}/u', $t)
            && ! in_array($ruta, ['cuanto_abonar', 'transferencia', 'identificador', 'insiste_saldo', 'cedula'], true)
            && empty($flags['cedula']) && empty($flags['cuanto_abonar']) && empty($flags['transferencia'])) {
            return 'pago_familiar_sin_ruta';
        }

        return null;
    }
}
