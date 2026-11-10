<?php

use App\Models\ChatConversacion;
use App\Models\ChatParticipante;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.usuario.{usuarioId}', function (User $user, string $usuarioId) {
    return (int) $user->usuario_id === (int) $usuarioId
        && $user->esStaff()
        && $user->tienePermiso('chat-interno.ver');
});

Broadcast::channel('chat.conversacion.{id}', function (User $user, string $id) {
    if (! $user->esStaff() || ! $user->tienePermiso('chat-interno.ver')) {
        return false;
    }

    return ChatParticipante::query()
        ->where('conversacion_id', (int) $id)
        ->where('usuario_id', $user->usuario_id)
        ->exists()
        && ChatConversacion::query()->whereKey((int) $id)->exists();
});
