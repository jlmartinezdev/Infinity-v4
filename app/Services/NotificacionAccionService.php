<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\User;
use App\Notifications\AccionSistemaNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Request;

class NotificacionAccionService
{
    /**
     * Campos de telemetría/app que no justifican campana admin.
     *
     * @var list<string>
     */
    private const CAMPOS_RUIDO = [
        'push_token',
        'device_type',
        'ultimo_acceso_at',
        'ultimo_acceso_ip',
        'remember_token',
        'updated_at',
        'created_at',
        'ultimo_ingreso',
        'dispositivo',
        'app_version',
        'app_activa',
        'fecha_activacion_app',
        'email_verified_at',
    ];

    /**
     * Notifica a administradores sobre auditoría del panel.
     * Nunca por acciones de la app cliente (portal): evita inundar la campana.
     */
    public function notificarAccion(Auditoria $auditoria): void
    {
        if ($this->debeOmitir($auditoria)) {
            return;
        }

        $auditoria->loadMissing('usuario');

        // Solo rol Administrador, staff activo (nunca vendedor/cajero/técnico/portal)
        $usuarios = User::query()
            ->staff()
            ->activos()
            ->with('rol')
            ->get()
            ->filter(fn (User $u) => $u->esAdministrador())
            ->values();

        if ($usuarios->isEmpty()) {
            return;
        }

        Notification::send($usuarios, new AccionSistemaNotification($auditoria));
    }

    private function debeOmitir(Auditoria $auditoria): bool
    {
        $auditoria->loadMissing('usuario');

        // 1) Quien actúa es usuario portal (app cliente)
        if ($auditoria->usuario && $auditoria->usuario->esClientePortal()) {
            return true;
        }

        // 2) Auth actual es portal (por si usuario_id no quedó en la auditoría)
        $auth = auth()->user();
        if ($auth instanceof User && $auth->esClientePortal()) {
            return true;
        }

        // 3) Request de API portal / login cliente
        if ($this->esRequestAppCliente()) {
            return true;
        }

        // 4) El registro tocado es un usuario portal (login/FCM/etc. como "Sistema")
        if ($auditoria->tabla === 'users' && $this->registroEsUsuarioPortal($auditoria)) {
            return true;
        }

        // 5) Updates solo de telemetría en users/clientes
        if ($auditoria->accion === 'updated' && in_array($auditoria->tabla, ['users', 'clientes'], true)) {
            $campos = $this->camposModificados($auditoria);
            if ($campos !== [] && collect($campos)->every(fn ($c) => in_array($c, self::CAMPOS_RUIDO, true))) {
                return true;
            }
        }

        return false;
    }

    private function esRequestAppCliente(): bool
    {
        $req = Request::instance();
        if (! $req) {
            return false;
        }

        if ($req->is('api/v1/portal', 'api/v1/portal/*')) {
            return true;
        }

        // Login de la app cliente (documento + PLUS), no staff
        if ($req->is('api/v1/login') && strtolower((string) $req->input('tipo', '')) === 'cliente') {
            return true;
        }

        // Solicitud de alta pública desde la app
        if ($req->is('api/v1/portal/solicitud-alta')) {
            return true;
        }

        return false;
    }

    private function registroEsUsuarioPortal(Auditoria $auditoria): bool
    {
        $id = (int) ($auditoria->registro_id ?? 0);
        if ($id <= 0) {
            return false;
        }

        return User::query()
            ->where('usuario_id', $id)
            ->whereNotNull('cliente_id')
            ->exists();
    }

    /**
     * @return list<string>
     */
    private function camposModificados(Auditoria $auditoria): array
    {
        $detalles = $auditoria->detalles;
        if (is_string($detalles)) {
            $detalles = json_decode($detalles, true);
        }
        if (! is_array($detalles)) {
            return [];
        }

        $old = is_array($detalles['old'] ?? null) ? $detalles['old'] : [];
        $new = is_array($detalles['new'] ?? null) ? $detalles['new'] : [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        $changed = [];
        foreach ($keys as $key) {
            $a = $old[$key] ?? null;
            $b = $new[$key] ?? null;
            if ($a != $b) {
                $changed[] = (string) $key;
            }
        }

        return $changed;
    }
}
