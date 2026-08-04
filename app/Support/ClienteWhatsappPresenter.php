<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\User;
use App\Models\WhatsappContacto;
use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\WhatsAppService;

class ClienteWhatsappPresenter
{
    public function __construct(
        private readonly Cliente $cliente,
    ) {}

    /**
     * @return array{
     *   tiene: bool,
     *   telefono?: string|null,
     *   total?: int,
     *   chat_url?: string|null,
     *   mensajes?: list<array<string, mixed>>
     * }
     */
    public function toArray(?User $user = null): array
    {
        $telefonos = $this->resolverTelefonos();
        $mensajes = WhatsappMensaje::query()
            ->where(function ($q) use ($telefonos) {
                $q->where('cliente_id', $this->cliente->cliente_id);
                if ($telefonos !== []) {
                    $q->orWhereIn('telefono', $telefonos);
                }
            })
            ->orderBy('id')
            ->limit(200)
            ->get();

        $canMedia = $user?->tienePermiso('whatsapp.ver') ?? false;
        $canEdit = $user?->tienePermiso('whatsapp.editar') ?? false;
        $telefono = (string) ($mensajes->last()?->telefono ?? $telefonos[0] ?? '');

        $ultimaEntrada = $mensajes->last(fn (WhatsappMensaje $m) => $m->esEntrada());
        $fueraVentana = ! $ultimaEntrada
            || ($ultimaEntrada->created_at && $ultimaEntrada->created_at->lt(now()->subHours(24)));

        return [
            'tiene' => $mensajes->isNotEmpty() || $telefono !== '',
            'telefono' => $telefono !== '' ? $telefono : null,
            'total' => $mensajes->count(),
            'fuera_ventana' => $fueraVentana,
            'puede_enviar' => $canEdit && $telefono !== '',
            'chat_url' => $canMedia && $telefono !== ''
                ? route('whatsapp.mensajes', ['tel' => $telefono])
                : null,
            'plantilla_url' => $canEdit && $telefono !== ''
                ? route('whatsapp.enviar', ['telefono' => $telefono])
                : null,
            'mensajes' => $mensajes
                ->map(fn (WhatsappMensaje $m) => $this->formatMensaje($m, $canMedia))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<string>
     */
    private function resolverTelefonos(): array
    {
        $whatsapp = app(WhatsAppService::class);
        $telefonos = WhatsappContacto::query()
            ->where('cliente_id', $this->cliente->cliente_id)
            ->pluck('telefono')
            ->filter()
            ->all();

        $rawTel = trim((string) ($this->cliente->telefono ?? ''));
        if ($rawTel !== '') {
            $digits = preg_replace('/\D+/', '', $rawTel) ?: '';
            if ($digits !== '') {
                $telefonos[] = $whatsapp->normalizePhone($digits) ?? $digits;
            }
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($t) => trim((string) $t),
            $telefonos
        ))));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMensaje(WhatsappMensaje $m, bool $canMedia): array
    {
        $tieneMedia = filled(data_get($m->payload, '_local.path'))
            || in_array($m->tipo, ['audio', 'image', 'video', 'document', 'sticker'], true);

        $ubicacion = $this->datosUbicacion($m);
        $cuerpo = trim((string) ($m->cuerpo ?? ''));

        if ($cuerpo === '' && $m->tipo !== 'text') {
            $cuerpo = match ($m->tipo) {
                'image' => 'Imagen',
                'audio' => 'Audio',
                'video' => 'Video',
                'document' => 'Documento',
                'sticker' => 'Sticker',
                'location' => 'Ubicación compartida',
                default => $m->template_name ? 'Plantilla: '.$m->template_name : '['.$m->tipo.']',
            };
        }

        return [
            'id' => $m->id,
            'direccion' => $m->direccion,
            'entrada' => $m->esEntrada(),
            'tipo' => $m->tipo,
            'tipo_label' => $this->tipoLabel($m),
            'cuerpo' => $cuerpo,
            'template_name' => $m->template_name,
            'estado' => $m->estado,
            'estado_label' => $this->estadoLabel($m),
            'fallido' => $m->esFallido(),
            'hora' => $m->created_at?->format('H:i'),
            'dia' => $m->created_at?->format('Y-m-d'),
            'dia_label' => $m->created_at?->translatedFormat('d M Y'),
            'contexto_tipo' => $m->contexto_tipo,
            'media_url' => ($canMedia && $tieneMedia) ? route('whatsapp.media', $m) : null,
            'media_es_imagen' => $m->tipo === 'image' && $tieneMedia,
            'maps_url' => $ubicacion['url'],
            'maps_label' => $ubicacion['nombre'] ?? $ubicacion['direccion'] ?? null,
        ];
    }

    private function tipoLabel(WhatsappMensaje $m): ?string
    {
        if ($m->tipo === 'text' || $m->tipo === 'unknown' || $m->tipo === null) {
            return null;
        }

        return match ($m->tipo) {
            'image' => 'Imagen',
            'audio' => 'Audio',
            'video' => 'Video',
            'document' => 'Documento',
            'sticker' => 'Sticker',
            'location' => 'Ubicación',
            'template' => 'Plantilla',
            default => ucfirst((string) $m->tipo),
        };
    }

    private function estadoLabel(WhatsappMensaje $m): ?string
    {
        if ($m->esEntrada()) {
            return null;
        }

        return match ($m->estado) {
            WhatsappMensaje::ESTADO_PENDIENTE => 'Pendiente',
            WhatsappMensaje::ESTADO_ENVIADO => 'Enviado',
            WhatsappMensaje::ESTADO_ENTREGADO => 'Entregado',
            WhatsappMensaje::ESTADO_LEIDO => 'Leído',
            WhatsappMensaje::ESTADO_FALLIDO => 'No enviado',
            WhatsappMensaje::ESTADO_RECIBIDO => 'Recibido',
            default => $m->estado,
        };
    }

    /**
     * @return array{lat:?float,lng:?float,nombre:?string,direccion:?string,url:?string}
     */
    private function datosUbicacion(WhatsappMensaje $m): array
    {
        $empty = ['lat' => null, 'lng' => null, 'nombre' => null, 'direccion' => null, 'url' => null];

        $lat = data_get($m->payload, 'location.latitude');
        $lng = data_get($m->payload, 'location.longitude');
        $nombre = trim((string) data_get($m->payload, 'location.name', '')) ?: null;
        $direccion = trim((string) data_get($m->payload, 'location.address', '')) ?: null;

        if (($lat === null || $lng === null) && is_string($m->cuerpo) && preg_match(
            '/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/',
            $m->cuerpo,
            $match
        )) {
            $lat = $match[1];
            $lng = $match[2];
        }

        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            return $empty;
        }

        $latF = (float) $lat;
        $lngF = (float) $lng;
        if ($latF < -90 || $latF > 90 || $lngF < -180 || $lngF > 180) {
            return $empty;
        }

        return [
            'lat' => $latF,
            'lng' => $lngF,
            'nombre' => $nombre,
            'direccion' => $direccion,
            'url' => 'https://www.google.com/maps?q='.rawurlencode($latF.','.$lngF),
        ];
    }
}
