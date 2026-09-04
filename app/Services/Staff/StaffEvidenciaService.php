<?php

namespace App\Services\Staff;

use App\Models\StaffEvidencia;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffEvidenciaService
{
    public function __construct(
        private readonly StaffVisitaService $visitas,
        private readonly StaffPedidoInstalacionService $pedidos,
    ) {}

    /**
     * @return array{id: string, url: string, entity_type: string, entity_id: string, caption: string|null, client_photo_id: string|null}
     */
    public function guardarVisita(
        User $user,
        int $visitaId,
        UploadedFile $foto,
        ?string $caption = null,
        ?string $clientPhotoId = null,
    ): array {
        $ticket = $this->visitas->encontrarAccesible($user, $visitaId);
        if (! $ticket) {
            throw ValidationException::withMessages([
                'visita' => ['Visita no encontrada o sin permiso.'],
            ]);
        }

        return $this->guardar(
            $user,
            StaffEvidencia::TIPO_VISITA,
            (int) $ticket->id,
            $foto,
            $caption,
            $clientPhotoId,
            'evidencia/visitas'
        );
    }

    /**
     * @return array{id: string, url: string, entity_type: string, entity_id: string, caption: string|null, client_photo_id: string|null}
     */
    public function guardarPedido(
        User $user,
        int $pedidoId,
        UploadedFile $foto,
        ?string $caption = null,
        ?string $clientPhotoId = null,
    ): array {
        $pedido = $this->pedidos->encontrar($user, $pedidoId);
        if (! $pedido) {
            throw ValidationException::withMessages([
                'pedido' => ['Pedido no encontrado o sin permiso.'],
            ]);
        }

        return $this->guardar(
            $user,
            StaffEvidencia::TIPO_PEDIDO,
            (int) $pedido->pedido_id,
            $foto,
            $caption,
            $clientPhotoId,
            'evidencia/pedidos'
        );
    }

    /**
     * @return array{id: string, url: string, entity_type: string, entity_id: string, caption: string|null, client_photo_id: string|null}
     */
    private function guardar(
        User $user,
        string $entityType,
        int $entityId,
        UploadedFile $foto,
        ?string $caption,
        ?string $clientPhotoId,
        string $carpeta,
    ): array {
        $mime = (string) $foto->getMimeType();
        if (! str_starts_with($mime, 'image/')) {
            throw ValidationException::withMessages([
                'foto' => ['El archivo debe ser una imagen.'],
            ]);
        }

        $clientPhotoId = filled($clientPhotoId) ? Str::lower(trim($clientPhotoId)) : null;

        return DB::transaction(function () use ($user, $entityType, $entityId, $foto, $caption, $clientPhotoId, $carpeta) {
            if ($clientPhotoId) {
                $existente = StaffEvidencia::query()
                    ->where('entity_type', $entityType)
                    ->where('entity_id', $entityId)
                    ->where('client_photo_id', $clientPhotoId)
                    ->lockForUpdate()
                    ->first();
                if ($existente) {
                    return $existente->toApiArray();
                }
            }

            $path = $foto->store($carpeta, 'public');
            if (! $path) {
                throw ValidationException::withMessages([
                    'foto' => ['No se pudo guardar la imagen.'],
                ]);
            }

            $evidencia = StaffEvidencia::create([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'usuario_id' => $user->usuario_id,
                'path' => $path,
                'caption' => filled($caption) ? Str::limit(trim($caption), 500, '') : null,
                'client_photo_id' => $clientPhotoId,
            ]);

            // Compat: primera foto de visita también en tickets.imagen
            if ($entityType === StaffEvidencia::TIPO_VISITA) {
                $ticket = Ticket::query()->find($entityId);
                if ($ticket && blank($ticket->imagen)) {
                    $ticket->imagen = $path;
                    $ticket->save();
                }
            }

            return $evidencia->toApiArray();
        });
    }
}
