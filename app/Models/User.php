<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Auditoria;
use App\Models\Rol;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Auditable, HasApiTokens, HasFactory, Notifiable;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'usuario_id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'telefono',
        'cedula',
        'cargo',
        'salario_basico',
        'banco',
        'cuenta_bancaria',
        'push_token',
        'device_type',
        'contrasena',
        'rol_id',
        'cliente_id',
        'permisos',
        'acceso_rapido',
        'estado',
        'ultimo_acceso_at',
        'ultimo_acceso_ip',
        'notas',
        'remember_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'contrasena',
        'remember_token',
    ];

    /**
     * Get the password for authentication.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return (string) ($this->contrasena ?? '');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
            'permisos' => 'array',
            'acceso_rapido' => 'array',
            'salario_basico' => 'integer',
            // No usar 'hashed' cast aquí porque interfiere con Auth::attempt()
            // La contraseña se hashea manualmente al crear/actualizar
        ];
    }

    public function registrarAcceso(?string $ip = null): void
    {
        $this->forceFill([
            'ultimo_acceso_at' => now(),
            'ultimo_acceso_ip' => $ip,
        ])->save();
    }

    /**
     * Relación con Rol
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id', 'rol_id');
    }

    /**
     * Cliente vinculado cuando el usuario es de portal app.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function auditorias(): HasMany
    {
        return $this->hasMany(Auditoria::class, 'usuario_id', 'usuario_id');
    }

    public function esClientePortal(): bool
    {
        return $this->cliente_id !== null;
    }

    public function esStaff(): bool
    {
        return ! $this->esClientePortal();
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereNull('cliente_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Obtener permisos como array
     */
    public function getPermisosAttribute($value)
    {
        if (empty($value)) {
            return [];
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded ?: [];
        }
        
        return $value ?: [];
    }

    /**
     * Establecer permisos como JSON
     */
    public function setPermisosAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['permisos'] = json_encode($value);
        } else {
            $this->attributes['permisos'] = $value;
        }
    }

    public function esAdministrador(): bool
    {
        return $this->rol && strtolower($this->rol->descripcion) === 'administrador';
    }

    /**
     * Puede ver flota GPS de técnicos (app staff admin + panel web).
     * Roles: admin, administrador, gerente — o permiso staff-mapa-tecnicos.ver.
     */
    public function puedeVerFlotaStaff(): bool
    {
        if ($this->esAdministrador()) {
            return true;
        }

        if ($this->tienePermiso('staff-mapa-tecnicos.ver')) {
            return true;
        }

        $rol = strtolower(trim((string) ($this->rol?->descripcion ?? '')));

        return in_array($rol, ['admin', 'administrador', 'gerente'], true);
    }

    /**
     * Ve todas las visitas staff (asignadas y sin asignar): admin / gerente / cajero.
     * Técnicos solo ven las asignadas a ellos.
     */
    public function puedeVerTodasVisitasStaff(): bool
    {
        if ($this->puedeVerFlotaStaff()) {
            return true;
        }

        $rol = strtolower(trim((string) ($this->rol?->descripcion ?? '')));

        return in_array($rol, ['cajero', 'gerente', 'admin', 'administrador'], true);
    }

    /**
     * Verificar si el usuario tiene un permiso específico.
     * Administrador tiene todos. Si no, solo se consideran los permisos individuales (checkboxes) del usuario.
     * El menú y las comprobaciones dependen de estos checkboxes, no del rol.
     */
    public function tienePermiso($permiso): bool
    {
        if ($this->esAdministrador()) {
            return true;
        }

        $permisosUsuario = is_array($this->permisos) ? $this->permisos : [];

        // Clientes portal: solo códigos portal.* (paquete global aplicado a todos)
        if ($this->esClientePortal()) {
            if (! str_starts_with((string) $permiso, 'portal.')) {
                return false;
            }

            return in_array($permiso, $permisosUsuario, true);
        }

        if (in_array($permiso, $permisosUsuario, true)) {
            return true;
        }
        foreach (\App\Support\PermisosCatalogo::compatiblesCon((string) $permiso) as $codigoGranular) {
            if (in_array($codigoGranular, $permisosUsuario, true)) {
                return true;
            }
        }

        return false;
    }
}
