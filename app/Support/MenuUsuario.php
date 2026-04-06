<?php

namespace App\Support;

use App\Models\User;

/**
 * Menú lateral y accesos rápidos filtrados por permisos del usuario.
 */
class MenuUsuario
{
    /**
     * Ítems de menú (con submenús anidados) visibles para el usuario.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function itemsFiltrados(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $items = config('menu.items', []);
        $user->loadMissing('rol.permisos');

        $esAdmin = $user->rol && strtolower($user->rol->descripcion) === 'administrador';

        $filter = function (array $items) use (&$filter, $user, $esAdmin): array {
            $out = [];
            foreach ($items as $item) {
                if (! empty($item['admin_only']) && ! $esAdmin) {
                    continue;
                }
                $permiso = $item['permiso'] ?? null;
                if ($permiso && ! $user->tienePermiso($permiso)) {
                    continue;
                }
                if (isset($item['submenu'])) {
                    $sub = $filter($item['submenu']);
                    if ($sub !== []) {
                        $item['submenu'] = $sub;
                        $out[] = $item;
                    }
                } else {
                    $out[] = $item;
                }
            }

            return $out;
        };

        return $filter($items);
    }

    /**
     * Lista plana de enlaces (etiqueta + ruta + grupo) para el panel sin estadísticas.
     * Excluye el ítem "Inicio" del menú.
     *
     * @return array<int, array{label: string, path: string, grupo: string|null}>
     */
    public static function enlacesPlanos(?User $user): array
    {
        $items = self::itemsFiltrados($user);
        $links = [];

        foreach ($items as $item) {
            if (($item['name'] ?? '') === 'home') {
                continue;
            }
            if (! empty($item['submenu'])) {
                $grupo = $item['label'] ?? null;
                foreach ($item['submenu'] as $sub) {
                    $links[] = [
                        'label' => $sub['label'] ?? '',
                        'path' => $sub['path'] ?? '#',
                        'grupo' => $grupo,
                    ];
                }
            } else {
                $links[] = [
                    'label' => $item['label'] ?? '',
                    'path' => $item['path'] ?? '#',
                    'grupo' => null,
                ];
            }
        }

        return $links;
    }
}
