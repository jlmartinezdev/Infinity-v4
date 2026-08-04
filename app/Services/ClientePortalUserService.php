<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use App\Support\PermisosCatalogo;
use Illuminate\Support\Facades\Hash;

class ClientePortalUserService
{
    public const ROL_CLIENTE_APP = 'Cliente App';

    public const EMAIL_DOMAIN = 'portal.cliente';

    /**
     * Extrae solo dígitos del documento (CI/RUC).
     */
    public static function normalizarDocumento(?string $documento): string
    {
        return preg_replace('/\D+/', '', trim((string) $documento)) ?? '';
    }

    /**
     * Quita separadores típicos (espacios, puntos, guiones) sin borrar letras.
     */
    public static function documentoSinSeparadores(?string $documento): string
    {
        return preg_replace('/[\s.\-\/]+/', '', trim((string) $documento)) ?? '';
    }

    public function emailPortalParaDocumento(string $documentoNormalizado): string
    {
        return $documentoNormalizado.'@'.self::EMAIL_DOMAIN;
    }

    public function asegurarRolClienteApp(): Rol
    {
        return Rol::firstOrCreate(
            ['descripcion' => self::ROL_CLIENTE_APP]
        );
    }

    /**
     * Busca cliente por documento.
     * - Match exacto del texto ingresado.
     * - Si solo hay dígitos/separadores: match por dígitos (mín. 5) para CI con puntos/guiones.
     * - Si el documento trae letras (ej. 1234tt), NO se cruza por solo dígitos
     *   (evita vincular 1234tt → cliente con cédula 1234).
     */
    public function buscarClientePorDocumento(string $documento): ?Cliente
    {
        $raw = trim($documento);
        if ($raw === '') {
            return null;
        }

        $cliente = Cliente::where('cedula', $raw)->first();
        if ($cliente) {
            return $cliente;
        }

        $sinSep = self::documentoSinSeparadores($raw);
        if ($sinSep !== '' && strcasecmp($sinSep, $raw) !== 0) {
            $cliente = Cliente::query()
                ->whereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(cedula, '.', ''), '-', ''), ' ', ''), '/', '') = ?",
                    [$sinSep]
                )
                ->first();
            if ($cliente) {
                return $cliente;
            }
        }

        // Documento con letras: no usar fallback solo-dígitos.
        if (preg_match('/[A-Za-z]/', $sinSep)) {
            return null;
        }

        $digits = self::normalizarDocumento($raw);
        // CI muy cortas no deben matchear solo por dígitos (evita cruces ambiguos).
        if (strlen($digits) < 5) {
            return null;
        }

        // Cédula en BD con separadores (1.234.567) vs dígitos puros.
        return Cliente::query()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(cedula, '.', ''), '-', ''), ' ', ''), '/', '') = ?",
                [$digits]
            )
            ->first();
    }

    /**
     * Crea o actualiza el usuario de portal del cliente.
     * Usuario = documento (email portal). Contraseña vacía hasta aprobación / clave PLUS.
     *
     * @param  bool  $resetPassword  Si true, deja la contraseña vacía (no usa el documento).
     * @return array{user: User, created: bool, password_reset: bool}
     */
    public function syncParaCliente(Cliente $cliente, bool $resetPassword = false): array
    {
        $digits = self::normalizarDocumento($cliente->cedula);
        if ($digits === '') {
            throw new \InvalidArgumentException("Cliente #{$cliente->cliente_id} sin documento válido.");
        }

        $rol = $this->asegurarRolClienteApp();
        $email = $this->emailPortalParaDocumento($digits);
        $nombre = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? '')) ?: ('Cliente '.$digits);

        $user = User::where('cliente_id', $cliente->cliente_id)->first();

        $created = false;
        $passwordReset = false;

        if (! $user) {
            $existingEmail = User::where('email', $email)->first();
            if ($existingEmail && ! $existingEmail->cliente_id) {
                throw new \RuntimeException("El email portal {$email} ya está usado por otro usuario.");
            }
            if ($existingEmail && (int) $existingEmail->cliente_id === (int) $cliente->cliente_id) {
                $user = $existingEmail;
            }
        }

        $permisosPortal = $this->permisosParaNuevoUsuarioPortal();

        if (! $user) {
            try {
                $user = new User;
                $user->cliente_id = $cliente->cliente_id;
                $user->rol_id = $rol->rol_id;
                $user->name = $nombre;
                $user->email = $email;
                $user->contrasena = null;
                $user->permisos = $permisosPortal;
                $user->estado = $this->estadoUsuarioDesdeCliente($cliente);
                $user->notas = 'Usuario portal app (sin contraseña hasta aprobación / alta).';
                $user->save();
                $created = true;
                $passwordReset = true;
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                $user = User::where('cliente_id', $cliente->cliente_id)
                    ->orWhere('email', $email)
                    ->first();
                if (! $user) {
                    throw $e;
                }
            }
        }

        if (! $created) {
            $user->rol_id = $rol->rol_id;
            $user->cliente_id = $cliente->cliente_id;
            $user->name = $nombre;
            $user->email = $email;
            $actuales = is_array($user->permisos) ? $user->permisos : [];
            if ($actuales === []) {
                $user->permisos = $permisosPortal;
            }
            // No pisar estado: el admin gestiona alta/baja de acceso app por separado.

            if ($resetPassword) {
                $user->contrasena = null;
                $passwordReset = true;
            }

            $user->save();
        }

        return [
            'user' => $user->fresh(['rol', 'cliente']),
            'created' => $created,
            'password_reset' => $passwordReset,
        ];
    }

    public function estadoUsuarioDesdeCliente(Cliente $cliente): string
    {
        $estado = strtolower((string) $cliente->estado);

        return in_array($estado, ['activo', 'solo_pedido'], true) ? 'activo' : 'suspendido';
    }

    /**
     * Asegura permisos portal en BD y los asocia al rol Cliente App (default: todos).
     * Si el rol no tenía permisos portal, también los propaga a los usuarios.
     *
     * @return list<string> códigos vigentes del rol
     */
    public function asegurarPermisosPortalEnRol(): array
    {
        $rol = $this->asegurarRolClienteApp();
        $codigosPortal = PermisosCatalogo::todosCodigosPortal();

        foreach (PermisosCatalogo::filasParaSeeder() as $fila) {
            if (! in_array($fila['codigo'], $codigosPortal, true)) {
                continue;
            }
            Permiso::updateOrCreate(['codigo' => $fila['codigo']], $fila);
        }

        $idsPortal = Permiso::whereIn('codigo', $codigosPortal)->pluck('id');
        $teníaPermisos = $rol->permisos()->whereIn('permisos.id', $idsPortal)->exists();
        if (! $teníaPermisos) {
            $rol->permisos()->syncWithoutDetaching($idsPortal->all());
            $this->aplicarPermisosGlobalesPortal($codigosPortal);
        }

        $rol->load('permisos');

        return $rol->permisos
            ->pluck('codigo')
            ->filter(fn ($c) => in_array($c, $codigosPortal, true))
            ->values()
            ->all();
    }

    /**
     * Códigos de permiso portal actuales (paquete global).
     *
     * @return list<string>
     */
    public function permisosGlobalesPortal(): array
    {
        $codigos = $this->asegurarPermisosPortalEnRol();
        $validos = array_flip(PermisosCatalogo::todosCodigosPortal());

        return array_values(array_filter($codigos, fn ($c) => isset($validos[$c])));
    }

    /**
     * Guarda el paquete global en el rol y lo propaga a todos los usuarios portal.
     *
     * @param  list<string>  $codigos
     * @return array{aplicados: int, codigos: list<string>}
     */
    public function aplicarPermisosGlobalesPortal(array $codigos): array
    {
        $validos = array_flip(PermisosCatalogo::todosCodigosPortal());
        $codigos = array_values(array_filter(
            array_unique($codigos),
            fn ($c) => is_string($c) && isset($validos[$c])
        ));

        foreach (PermisosCatalogo::filasParaSeeder() as $fila) {
            if (! isset($validos[$fila['codigo']])) {
                continue;
            }
            Permiso::updateOrCreate(['codigo' => $fila['codigo']], $fila);
        }

        $rol = $this->asegurarRolClienteApp();
        $ids = Permiso::whereIn('codigo', $codigos)->pluck('id')->all();
        $rol->permisos()->sync($ids);

        $aplicados = 0;
        $rolId = $rol->rol_id;
        User::whereNotNull('cliente_id')
            ->orderBy('usuario_id')
            ->chunkById(100, function ($users) use ($codigos, $rolId, &$aplicados) {
                foreach ($users as $user) {
                    $user->permisos = $codigos;
                    $user->rol_id = $rolId;
                    $user->save();
                    $aplicados++;
                }
            }, 'usuario_id');

        return [
            'aplicados' => $aplicados,
            'codigos' => $codigos,
        ];
    }

    /**
     * Permisos a asignar a un usuario portal nuevo (paquete global actual).
     *
     * @return list<string>
     */
    public function permisosParaNuevoUsuarioPortal(): array
    {
        $actuales = $this->permisosGlobalesPortal();
        if ($actuales !== []) {
            return $actuales;
        }

        return PermisosCatalogo::todosCodigosPortal();
    }

    /**
     * Genera contraseña portal: PLUS + 4 dígitos (ej. PLUS5685).
     */
    public function generarClavePlus(): string
    {
        return 'PLUS'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * True si el hash actual coincide con el documento del cliente (legacy).
     */
    public function contrasenaEsDocumento(User $user, ?Cliente $cliente = null): bool
    {
        $hash = (string) ($user->contrasena ?? '');
        if ($hash === '') {
            return false;
        }
        $cliente ??= $user->cliente;
        $cedula = (string) ($cliente?->cedula ?? '');
        $digits = self::normalizarDocumento($cedula);
        if ($digits !== '' && Hash::check($digits, $hash)) {
            return true;
        }

        return $cedula !== '' && Hash::check($cedula, $hash);
    }

    /**
     * Vacía contraseñas portal = documento, salvo clientes con app ya activa
     * o con fecha de otorgamiento (conservan su clave actual / PLUS).
     *
     * @return int cantidad limpiada
     */
    public function limpiarContrasenasDocumentoLegacy(): int
    {
        $sinUsoApp = User::query()
            ->whereNotNull('cliente_id')
            ->whereNotNull('contrasena')
            ->whereHas('cliente', function ($q) {
                $q->whereNull('fecha_otorgamiento')
                    ->where(function ($q2) {
                        $q2->where('app_activa', false)->orWhereNull('app_activa');
                    });
            })
            ->update(['contrasena' => null]);

        $limpiadosHash = 0;
        User::query()
            ->whereNotNull('cliente_id')
            ->whereNotNull('contrasena')
            ->whereHas('cliente', function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('fecha_otorgamiento')
                        ->orWhere('app_activa', true);
                });
            })
            ->with('cliente:cliente_id,cedula')
            ->orderBy('usuario_id')
            ->chunkById(100, function ($users) use (&$limpiadosHash) {
                foreach ($users as $user) {
                    // App activa / otorgado: solo vaciar si el hash sigue siendo el documento.
                    // Si es PLUS u otra, conservar.
                    if (! $this->contrasenaEsDocumento($user)) {
                        continue;
                    }
                    // Quien ya usa la app con CI: conservar (no cortar acceso).
                    if ($user->cliente?->app_activa) {
                        continue;
                    }
                    $user->contrasena = null;
                    $user->save();
                    $limpiadosHash++;
                }
            }, 'usuario_id');

        return (int) $sinUsoApp + $limpiadosHash;
    }

    /**
     * Restaura contraseña = documento solo a clientes con app activa y sin clave
     * (recuperación tras limpieza masiva; no aplica a altas nuevas).
     *
     * @return int
     */
    public function restaurarDocumentoSiAppActivaSinClave(): int
    {
        $restaurados = 0;
        User::query()
            ->whereNotNull('cliente_id')
            ->whereNull('contrasena')
            ->whereHas('cliente', fn ($q) => $q->where('app_activa', true))
            ->with('cliente:cliente_id,cedula')
            ->orderBy('usuario_id')
            ->chunkById(100, function ($users) use (&$restaurados) {
                foreach ($users as $user) {
                    $digits = self::normalizarDocumento($user->cliente?->cedula);
                    if ($digits === '') {
                        continue;
                    }
                    $user->contrasena = Hash::make($digits);
                    $user->save();
                    $restaurados++;
                }
            }, 'usuario_id');

        return $restaurados;
    }

    /**
     * Autentica cliente de portal.
     * Usuario = documento. Contraseña = clave otorgada (PLUS**** u otra definida en alta).
     * Sin contraseña cargada → no puede iniciar sesión.
     */
    public function autenticarPorDocumento(string $usuario, string $password): ?User
    {
        $docUsuario = self::normalizarDocumento($usuario);
        if ($docUsuario === '') {
            return null;
        }

        $cliente = $this->buscarClientePorDocumento($docUsuario);
        if (! $cliente) {
            return null;
        }

        $result = $this->syncParaCliente($cliente, false);
        $user = $result['user'];

        if ($user->estado !== 'activo') {
            return null;
        }

        $hash = (string) ($user->contrasena ?? '');
        if ($hash === '') {
            return null;
        }

        if (Hash::check($password, $hash)) {
            return $user;
        }

        // Transición: si aún tienen hash = documento (app activa previa), permitir CI.
        $docPassword = self::normalizarDocumento($password);
        if ($docPassword !== '' && Hash::check($docPassword, $hash)) {
            return $user;
        }
        if (Hash::check((string) $cliente->cedula, $hash)) {
            return $user;
        }

        return null;
    }

    /**
     * Asigna clave PLUS al usuario portal del cliente (crea usuario si no existe).
     *
     * @return array{user: User, clave: string, created: bool}
     */
    public function otorgarAccesoConClavePlus(Cliente $cliente, ?string $clave = null): array
    {
        $clave = $clave ?: $this->generarClavePlus();
        $sync = $this->syncParaCliente($cliente, false);
        $user = $sync['user'];
        $user->contrasena = Hash::make($clave);
        $user->estado = 'activo';
        $user->save();

        if (! $cliente->fecha_otorgamiento) {
            $cliente->fecha_otorgamiento = now();
            $cliente->save();
        }

        try {
            app(\App\Services\Loyalty\PuntosService::class)->aplicarBienvenidaUnaVez(
                (int) $cliente->cliente_id,
                [
                    'meta' => [
                        'motivo' => 'otorgamiento_acceso_app',
                        'cliente_id' => $cliente->cliente_id,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('[Loyalty] Bienvenida omitida: '.$e->getMessage());
        }

        return [
            'user' => $user->fresh(['rol', 'cliente']),
            'clave' => $clave,
            'created' => $sync['created'],
        ];
    }

    /**
     * Suspende acceso app: no puede loguear; cierra tokens Sanctum y push.
     */
    public function suspenderAcceso(User $user): User
    {
        if (! $user->esClientePortal()) {
            throw new \InvalidArgumentException('Solo usuarios portal.');
        }

        $user->estado = 'suspendido';
        $user->push_token = null;
        $user->save();
        $user->tokens()->delete();

        if ($user->cliente_id) {
            Cliente::where('cliente_id', $user->cliente_id)->update(['app_activa' => false]);
        }

        return $user->fresh(['cliente']);
    }

    /**
     * Reactiva acceso app (independiente del estado del cliente ISP).
     */
    public function reactivarAcceso(User $user): User
    {
        if (! $user->esClientePortal()) {
            throw new \InvalidArgumentException('Solo usuarios portal.');
        }

        $user->estado = 'activo';
        $user->save();

        return $user->fresh(['cliente']);
    }

    /**
     * Elimina por completo el usuario portal (el cliente ISP permanece).
     */
    public function eliminarAcceso(User $user): void
    {
        if (! $user->esClientePortal()) {
            throw new \InvalidArgumentException('Solo usuarios portal.');
        }

        $clienteId = $user->cliente_id;
        $user->tokens()->delete();
        $user->delete();

        if ($clienteId) {
            Cliente::where('cliente_id', $clienteId)->update([
                'app_activa' => false,
                'fecha_otorgamiento' => null,
                'aprobado_por' => null,
            ]);
        }
    }
}
