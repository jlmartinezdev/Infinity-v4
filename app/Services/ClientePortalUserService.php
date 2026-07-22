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
     * Busca cliente por documento (exacto o solo dígitos).
     */
    public function buscarClientePorDocumento(string $documento): ?Cliente
    {
        $raw = trim($documento);
        $digits = self::normalizarDocumento($raw);
        if ($digits === '') {
            return null;
        }

        $cliente = Cliente::where('cedula', $raw)->first();
        if ($cliente) {
            return $cliente;
        }

        return Cliente::query()
            ->whereRaw("REPLACE(REPLACE(REPLACE(cedula, '.', ''), '-', ''), ' ', '') = ?", [$digits])
            ->first();
    }

    /**
     * Crea o actualiza el usuario de portal del cliente.
     * Usuario y contraseña inicial = número de documento (solo dígitos).
     *
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
                $user->contrasena = Hash::make($digits);
                $user->permisos = $permisosPortal;
                $user->estado = $this->estadoUsuarioDesdeCliente($cliente);
                $user->notas = 'Usuario portal app (documento = usuario/contraseña).';
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
            $user->estado = $this->estadoUsuarioDesdeCliente($cliente);

            if ($resetPassword) {
                $user->contrasena = Hash::make($digits);
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
     * Autentica cliente de portal.
     * Usuario = documento. Contraseña = clave PLUS**** o (legacy) el mismo documento.
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

        // Clave actual (PLUS5685 u otra) tal cual la envió la app
        if (Hash::check($password, $user->contrasena)) {
            return $user;
        }

        // Legacy: documento como contraseña (solo dígitos o cédula cruda)
        $docPassword = self::normalizarDocumento($password);
        if ($docPassword !== '' && Hash::check($docPassword, $user->contrasena)) {
            return $user;
        }
        if (Hash::check((string) $cliente->cedula, $user->contrasena)) {
            return $user;
        }

        return null;
    }
}
