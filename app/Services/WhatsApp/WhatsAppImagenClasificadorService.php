<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappMensaje;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Lee el contenido de una foto de WhatsApp (visión / texto visible)
 * para distinguir comprobante, mapa u otra captura.
 */
class WhatsAppImagenClasificadorService
{
    public const TIPO_COMPROBANTE = 'comprobante';

    public const TIPO_MAPA = 'mapa';

    public const TIPO_ONU = 'onu';

    public const TIPO_OTRO = 'otro';

    public const TIPO_DESCONOCIDO = 'desconocido';

    public function __construct(
        private readonly WhatsAppService $whatsapp,
    ) {}

    /**
     * @return array{tipo: string, ocr: string, fuente: string|null, descripcion: string}
     */
    public function clasificar(WhatsappMensaje $mensaje): array
    {
        $vacio = [
            'tipo' => self::TIPO_DESCONOCIDO,
            'ocr' => '',
            'fuente' => null,
            'descripcion' => '',
        ];

        if ($mensaje->tipo !== 'image') {
            return $vacio;
        }

        $relative = $this->whatsapp->rutaMediaLocal($mensaje);
        if ($relative === null) {
            $mensaje = $this->whatsapp->adjuntarMediaLocal($mensaje);
            $relative = $this->whatsapp->rutaMediaLocal($mensaje);
        }
        if ($relative === null || ! Storage::disk('local')->exists($relative)) {
            return $vacio;
        }

        try {
            $binario = Storage::disk('local')->get($relative);
        } catch (\Throwable) {
            return $vacio;
        }

        if (! is_string($binario) || strlen($binario) < 32) {
            return $vacio;
        }

        $vision = $this->clasificarConGemini($binario);
        $ocr = trim((string) ($vision['ocr'] ?? ''));
        $tipoVision = (string) ($vision['tipo'] ?? '');
        $tipoTexto = self::clasificarDesdeTexto($ocr);
        $tipo = self::resolverTipo($tipoVision, $tipoTexto);

        $descripcion = match ($tipo) {
            self::TIPO_MAPA => 'captura de mapa / ubicación',
            self::TIPO_COMPROBANTE => 'comprobante de transferencia',
            self::TIPO_ONU => 'foto de equipo / ONU',
            default => $ocr !== '' ? mb_substr($ocr, 0, 120) : 'foto',
        };

        Log::info('[WA agent] Imagen clasificada', [
            'mensaje_id' => $mensaje->id,
            'tipo' => $tipo,
            'fuente' => $vision['fuente'] ?? null,
            'ocr_len' => mb_strlen($ocr),
        ]);

        return [
            'tipo' => $tipo,
            'ocr' => mb_substr($ocr, 0, 600),
            'fuente' => $vision['fuente'] ?? null,
            'descripcion' => $descripcion,
        ];
    }

    public static function resolverTipo(string $tipoVision, string $tipoTexto): string
    {
        $validos = [
            self::TIPO_COMPROBANTE,
            self::TIPO_MAPA,
            self::TIPO_ONU,
            self::TIPO_OTRO,
        ];

        if ($tipoVision === self::TIPO_MAPA || $tipoTexto === self::TIPO_MAPA) {
            if ($tipoVision === self::TIPO_COMPROBANTE || $tipoTexto === self::TIPO_COMPROBANTE) {
                return self::TIPO_COMPROBANTE;
            }

            return self::TIPO_MAPA;
        }

        if (in_array($tipoVision, $validos, true) && $tipoVision !== self::TIPO_OTRO) {
            return $tipoVision;
        }

        if (in_array($tipoTexto, $validos, true) && $tipoTexto !== self::TIPO_OTRO) {
            return $tipoTexto;
        }

        if ($tipoVision === self::TIPO_OTRO || $tipoTexto === self::TIPO_OTRO) {
            return self::TIPO_OTRO;
        }

        return self::TIPO_DESCONOCIDO;
    }

    public static function clasificarDesdeTexto(string $texto): string
    {
        $t = mb_strtolower(trim($texto));
        if ($t === '') {
            return self::TIPO_DESCONOCIDO;
        }

        $mapa = (bool) preg_match(
            '/enviar ubicaci|ubicaci[oó]n actual|ubicaci[oó]n en tiempo real|google maps|maps\.google|mi ubicaci[oó]n|estaci[oó]n n[uú]mero|hip[oó]dromo|tiempo real/u',
            $t
        );
        $pago = (bool) preg_match(
            '/comprobante de transferencia|transferencia exitosa|ueno|tigo\s*money|gs\.\s*[\d.]{3,}|guaran[ií]|banco/u',
            $t
        );
        $onu = (bool) preg_match('/\b(onu|gpon|los|pon led|router|modem|m[oó]dem)\b/u', $t);

        if ($pago) {
            return self::TIPO_COMPROBANTE;
        }
        if ($mapa) {
            return self::TIPO_MAPA;
        }
        if ($onu) {
            return self::TIPO_ONU;
        }

        return self::TIPO_OTRO;
    }

    /**
     * @return array{tipo: string, ocr: string, fuente: string|null}
     */
    private function clasificarConGemini(string $binario): array
    {
        $vacio = ['tipo' => self::TIPO_DESCONOCIDO, 'ocr' => '', 'fuente' => null];
        $apiKey = trim((string) config('whatsapp.agent.gemini_api_key', ''));
        if ($apiKey === '') {
            return $vacio;
        }

        $jpeg = $this->jpegParaVision($binario);
        $prompt = <<<'TXT'
Clasificá esta captura de un cliente de internet (WhatsApp, Paraguay).
Respondé SOLO JSON, sin markdown: {"tipo":"comprobante"|"mapa"|"onu"|"otro","texto":"palabras visibles"}
- comprobante: recibo bancario, transferencia, ueno, Tigo Money, Gs, monto.
- mapa: Google Maps, "Enviar ubicación", pin, lista de lugares, captura de mapa.
- onu: foto de router/ONU/módem/LEDs.
- otro: cualquier otra foto.
TXT;

        $modelos = [];
        $preferido = preg_replace('#^models/#', '', (string) config('whatsapp.agent.gemini_model', 'gemini-2.5-flash'));
        foreach ([$preferido, 'gemini-3.6-flash', 'gemini-2.0-flash'] as $m) {
            $m = trim((string) $m);
            if ($m !== '' && ! in_array($m, $modelos, true)) {
                $modelos[] = $m;
            }
        }

        foreach ($modelos as $modelo) {
            try {
                $response = Http::timeout(12)
                    ->connectTimeout(4)
                    ->acceptJson()
                    ->post(
                        'https://generativelanguage.googleapis.com/v1beta/models/'.$modelo.':generateContent?key='.urlencode($apiKey),
                        [
                            'contents' => [[
                                'parts' => [
                                    ['text' => $prompt],
                                    ['inline_data' => [
                                        'mime_type' => $jpeg['mime'],
                                        'data' => $jpeg['data'],
                                    ]],
                                ],
                            ]],
                            'generationConfig' => [
                                'temperature' => 0,
                                'maxOutputTokens' => 256,
                            ],
                        ]
                    );
            } catch (\Throwable $e) {
                Log::notice('[WA agent] Visión Gemini excepción', [
                    'modelo' => $modelo,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if (! $response->successful()) {
                Log::notice('[WA agent] Visión Gemini HTTP', [
                    'modelo' => $modelo,
                    'status' => $response->status(),
                ]);

                continue;
            }

            $texto = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
            $parsed = self::parsearRespuestaVision($texto);
            if ($parsed === null && $texto !== '') {
                $tipoBruto = self::clasificarDesdeTexto($texto);
                if (preg_match('/"tipo"\s*:\s*"(comprobante|mapa|onu|otro)"/i', $texto, $tm)) {
                    $tipoBruto = mb_strtolower($tm[1]);
                }
                if ($tipoBruto !== self::TIPO_DESCONOCIDO) {
                    $parsed = [
                        'tipo' => $tipoBruto,
                        'ocr' => mb_substr(trim($texto), 0, 600),
                        'fuente' => null,
                    ];
                }
            }
            if ($parsed !== null) {
                $parsed['fuente'] = 'gemini:'.$modelo;

                return $parsed;
            }
        }

        return $vacio;
    }

    /**
     * @return array{tipo: string, ocr: string, fuente: null}|null
     */
    public static function parsearRespuestaVision(string $texto): ?array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }
        if (preg_match('/\{.*\}/s', $texto, $m)) {
            $texto = $m[0];
        }
        $json = json_decode($texto, true);
        if (! is_array($json) && preg_match('/"tipo"\s*:\s*"(comprobante|mapa|onu|otro)"/i', $texto, $tm)) {
            $ocr = $texto;
            if (preg_match('/"texto"\s*:\s*"(.*)"/s', $texto, $tm2)) {
                $ocr = stripcslashes($tm2[1]);
            }

            return [
                'tipo' => mb_strtolower($tm[1]),
                'ocr' => trim($ocr),
                'fuente' => null,
            ];
        }
        if (! is_array($json)) {
            return null;
        }
        $tipo = mb_strtolower(trim((string) ($json['tipo'] ?? '')));
        $ocr = trim((string) ($json['texto'] ?? $json['ocr'] ?? ''));
        $permitidos = [
            self::TIPO_COMPROBANTE,
            self::TIPO_MAPA,
            self::TIPO_ONU,
            self::TIPO_OTRO,
        ];
        if (! in_array($tipo, $permitidos, true)) {
            $tipo = self::clasificarDesdeTexto($ocr);
        }

        return [
            'tipo' => $tipo,
            'ocr' => $ocr,
            'fuente' => null,
        ];
    }

    /**
     * @return array{mime: string, data: string}
     */
    private function jpegParaVision(string $binario): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return ['mime' => 'image/jpeg', 'data' => base64_encode($binario)];
        }

        $img = @imagecreatefromstring($binario);
        if ($img === false) {
            return ['mime' => 'image/jpeg', 'data' => base64_encode($binario)];
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $max = 1024;
        if (max($w, $h) > $max) {
            $scale = $max / max($w, $h);
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $dst;
        }

        ob_start();
        imagejpeg($img, null, 72);
        $jpeg = (string) ob_get_clean();
        imagedestroy($img);

        return ['mime' => 'image/jpeg', 'data' => base64_encode($jpeg !== '' ? $jpeg : $binario)];
    }
}
