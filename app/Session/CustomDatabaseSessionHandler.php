<?php

namespace App\Session;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Schema;

/**
 * Manejador de sesión compatible con tablas que tienen usuario_id en lugar de user_id,
 * o que no tienen columna de usuario.
 */
class CustomDatabaseSessionHandler extends DatabaseSessionHandler
{
    /**
     * Solo agrega user_id si la columna existe en la tabla sessions.
     */
    protected function addUserInformation(&$payload): static
    {
        if (! Schema::hasColumn($this->table, 'user_id')) {
            return $this;
        }

        if ($this->container && $this->container->bound(Guard::class)) {
            $payload['user_id'] = $this->userId();
        }

        return $this;
    }
}
