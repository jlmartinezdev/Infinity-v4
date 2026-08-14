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
                if (! empty($item['flota_staff']) && ! $user->puedeVerFlotaStaff()) {
                    continue;
                }
                if (! self::entradaPermitida($user, $item, $esAdmin)) {
                    continue;
                }
                if (isset($item['submenu'])) {
                    $sub = $filter($item['submenu']);
                    // Acceso rápido se completa después aunque el catálogo quede vacío.
                    if ($sub !== [] || ($item['name'] ?? '') === 'acceso-rapido') {
                        $item['submenu'] = $sub;
                        $out[] = $item;
                    }
                } else {
                    $out[] = $item;
                }
            }

            return $out;
        };

        return self::aplicarAccesoRapidoPersonalizado($filter($items), $user);
    }

    /**
     * Entradas del catálogo visibles para el usuario (para la UI de personalización).
     *
     * @return list<array{name: string, label: string, path: string}>
     */
    public static function catalogoAccesoRapidoVisible(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $user->loadMissing('rol.permisos');
        $out = [];
        foreach (config('menu.acceso_rapido.catalogo', []) as $entry) {
            if (! is_array($entry) || ! self::entradaPermitida($user, $entry)) {
                continue;
            }
            $name = (string) ($entry['name'] ?? '');
            $label = (string) ($entry['label'] ?? '');
            $path = (string) ($entry['path'] ?? '');
            if ($name === '' || $label === '' || $path === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'label' => $label,
                'path' => $path,
            ];
        }

        return $out;
    }

    /**
     * Orden de names seleccionados (preferencia o default filtrado por lo visible).
     *
     * @return list<string>
     */
    public static function accesoRapidoSeleccionado(?User $user): array
    {
        $visibles = collect(self::catalogoAccesoRapidoVisible($user))->pluck('name')->all();
        $visiblesFlip = array_flip($visibles);

        $prefs = $user?->acceso_rapido;
        if ($prefs === null) {
            $orden = config('menu.acceso_rapido.default', []);
        } else {
            $orden = is_array($prefs) ? $prefs : [];
        }

        $out = [];
        foreach ($orden as $name) {
            if (! is_string($name) || $name === '' || ! isset($visiblesFlip[$name])) {
                continue;
            }
            $out[] = $name;
        }

        return $out;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    public static function sanitizarAccesoRapido(User $user, array $names): array
    {
        $permitidos = array_flip(
            collect(self::catalogoAccesoRapidoVisible($user))->pluck('name')->all()
        );
        $out = [];
        foreach ($names as $name) {
            if (! is_string($name) || $name === '' || ! isset($permitidos[$name])) {
                continue;
            }
            if (in_array($name, $out, true)) {
                continue;
            }
            $out[] = $name;
        }

        return $out;
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
            // Acceso rápido / Inicio: no listar como grupo de permisos (duplican ítems reales).
            if (in_array(($item['name'] ?? ''), ['home', 'acceso-rapido'], true)) {
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
     * Lista plana de enlaces (etiqueta + ruta + grupo + icono) para el panel sin estadísticas.
     * Excluye el ítem "Inicio" del menú.
     *
     * @return array<int, array{label: string, path: string, grupo: string|null, icon: string, name: string}>
     */
    public static function enlacesPlanos(?User $user): array
    {
        $items = self::itemsFiltrados($user);
        $links = [];

        foreach ($items as $item) {
            if (in_array(($item['name'] ?? ''), ['home', 'acceso-rapido'], true)) {
                continue;
            }
            $icon = (string) ($item['icon'] ?? 'document');
            if (! empty($item['submenu'])) {
                $grupo = $item['label'] ?? null;
                foreach ($item['submenu'] as $sub) {
                    $path = $sub['path'] ?? '#';
                    if (! is_string($path) || $path === '') {
                        continue;
                    }
                    $links[] = [
                        'label' => (string) ($sub['label'] ?? ''),
                        'path' => $path,
                        'grupo' => is_string($grupo) ? $grupo : null,
                        'icon' => $icon,
                        'name' => (string) ($sub['name'] ?? ''),
                    ];
                }
            } else {
                $path = $item['path'] ?? '#';
                if (! is_string($path) || $path === '') {
                    continue;
                }
                $links[] = [
                    'label' => (string) ($item['label'] ?? ''),
                    'path' => $path,
                    'grupo' => null,
                    'icon' => $icon,
                    'name' => (string) ($item['name'] ?? ''),
                ];
            }
        }

        return $links;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function aplicarAccesoRapidoPersonalizado(array $items, User $user): array
    {
        $catalogo = collect(config('menu.acceso_rapido.catalogo', []))->keyBy('name');
        $seleccion = self::accesoRapidoSeleccionado($user);

        $submenu = [];
        foreach ($seleccion as $name) {
            $entry = $catalogo->get($name);
            if (! is_array($entry)) {
                continue;
            }
            $submenu[] = $entry;
        }

        $submenu[] = [
            'name' => 'rapido-personalizar',
            'label' => 'Personalizar…',
            'path' => '/mi-acceso-rapido',
        ];

        $found = false;
        foreach ($items as $i => $item) {
            if (($item['name'] ?? '') !== 'acceso-rapido') {
                continue;
            }
            $items[$i]['submenu'] = $submenu;
            $found = true;
            break;
        }

        if (! $found) {
            array_unshift($items, [
                'name' => 'acceso-rapido',
                'label' => 'Acceso rápido',
                'icon' => 'bolt',
                'defaultExpanded' => true,
                'submenu' => $submenu,
            ]);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function entradaPermitida(User $user, array $item, ?bool $esAdmin = null): bool
    {
        if ($esAdmin === null) {
            $esAdmin = $user->rol && strtolower((string) $user->rol->descripcion) === 'administrador';
        }

        if (! empty($item['admin_only']) && ! $esAdmin) {
            return false;
        }
        if (! empty($item['flota_staff']) && ! $user->puedeVerFlotaStaff()) {
            return false;
        }

        $permiso = $item['permiso'] ?? null;
        if ($permiso && ! $user->tienePermiso($permiso)) {
            return false;
        }

        $permisoAny = $item['permiso_any'] ?? null;
        if (is_array($permisoAny) && $permisoAny !== []) {
            foreach ($permisoAny as $codigo) {
                if ($user->tienePermiso($codigo)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }
}
