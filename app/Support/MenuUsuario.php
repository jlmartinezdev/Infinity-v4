<?php

namespace App\Support;

use App\Models\Permiso;
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
     * Permisos definidos en config/menu.php (únicos, orden de aparición en el menú),
     * solo códigos que existen en la tabla permisos. Para la UI de asignación a usuarios.
     *
     * @return list<array{codigo: string, contexto: string, nombre: string}>
     */
    public static function permisosMenuParaUi(): array
    {
        $nombresPorCodigo = Permiso::query()->pluck('nombre', 'codigo')->all();
        $codigosValidos = array_flip(array_keys($nombresPorCodigo));

        /** @var array<string, array{contextos: list<string>}> $agrupados orden de inserción = orden de primera aparición en el menú */
        $agrupados = [];

        foreach (config('menu.items', []) as $item) {
            if (($item['name'] ?? '') === 'home') {
                continue;
            }
            if (! empty($item['admin_only'])) {
                continue;
            }

            $grupo = trim((string) ($item['label'] ?? ''));

            $addContexto = function (string $codigo, string $lineaContexto) use (&$agrupados, $codigosValidos): void {
                if ($codigo === '' || ! isset($codigosValidos[$codigo]) || $lineaContexto === '') {
                    return;
                }
                if (! isset($agrupados[$codigo])) {
                    $agrupados[$codigo] = ['contextos' => []];
                }
                if (! in_array($lineaContexto, $agrupados[$codigo]['contextos'], true)) {
                    $agrupados[$codigo]['contextos'][] = $lineaContexto;
                }
            };

            if (! empty($item['permiso'])) {
                $codigo = (string) $item['permiso'];
                $addContexto($codigo, $grupo);
            }
            foreach ($item['submenu'] ?? [] as $sub) {
                if (! empty($sub['permiso'])) {
                    $codigo = (string) $sub['permiso'];
                    $subEtiqueta = trim((string) ($sub['label'] ?? ''));
                    $linea = $subEtiqueta !== '' ? $grupo.' → '.$subEtiqueta : $grupo;
                    $addContexto($codigo, $linea);
                }
            }
        }

        $rows = [];
        foreach ($agrupados as $codigo => $meta) {
            $rows[] = [
                'codigo' => $codigo,
                'contexto' => implode(' · ', $meta['contextos']),
                'nombre' => $nombresPorCodigo[$codigo] ?? $codigo,
            ];
        }

        return $rows;
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
