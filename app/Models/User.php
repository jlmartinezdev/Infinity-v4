<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Auditoria;
use App\Models\Rol;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Auditable, HasFactory, Notifiable;

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
        'contrasena',
        'rol_id',
        'permisos',
        'estado',
        'notas',
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
        return $this->contrasena;
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
            'permisos' => 'array',
            // No usar 'hashed' cast aquí porque interfiere con Auth::attempt()
            // La contraseña se hashea manualmente al crear/actualizar
        ];
    }

    /**
     * Relación con Rol
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id', 'rol_id');
    }

    public function auditorias(): HasMany
    {
        return $this->hasMany(Auditoria::class, 'usuario_id', 'usuario_id');
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

    /**
     * Verificar si el usuario tiene un permiso específico.
     * Administrador tiene todos. Si no, solo se consideran los permisos individuales (checkboxes) del usuario.
     * El menú y las comprobaciones dependen de estos checkboxes, no del rol.
     */
    public function tienePermiso($permiso): bool
    {
        if ($this->rol && strtolower($this->rol->descripcion) === 'administrador') {
            return true;
        }
        $permisosUsuario = is_array($this->permisos) ? $this->permisos : [];
        return in_array($permiso, $permisosUsuario);
    }
}
