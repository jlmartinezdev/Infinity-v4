<?php

namespace App\Support;

class PermisosCatalogo
{
    /** @var array<string, string>|null */
    private static ?array $etiquetasAccion = null;

    /**
     * @return list<array{label: string, items: list<array{label: string, base: string, acciones: list<string>}>}>
     */
    public static function grupos(): array
    {
        return config('permisos.grupos', []);
    }

    /**
     * @return array<string, string>
     */
    public static function etiquetasAccion(): array
    {
        self::$etiquetasAccion ??= config('permisos.acciones_etiqueta', []);

        return self::$etiquetasAccion;
    }

    public static function codigo(string $base, string $accion): string
    {
        return $base.'.'.$accion;
    }

    /**
     * @return list<string>
     */
    public static function todosCodigos(): array
    {
        $codigos = [];
        foreach (self::grupos() as $grupo) {
            foreach ($grupo['items'] as $item) {
                foreach ($item['acciones'] as $accion) {
                    $codigos[] = self::codigo($item['base'], $accion);
                }
            }
        }

        return array_values(array_unique($codigos));
    }

    /**
     * Filas para PermisoSeeder.
     *
     * @return list<array{codigo: string, nombre: string, categoria: string, orden: int}>
     */
    public static function filasParaSeeder(): array
    {
        $filas = [];
        $orden = 0;
        foreach (self::grupos() as $grupo) {
            foreach ($grupo['items'] as $item) {
                foreach ($item['acciones'] as $accion) {
                    $orden++;
                    $filas[] = [
                        'codigo' => self::codigo($item['base'], $accion),
                        'nombre' => $item['label'].' — '.(self::etiquetasAccion()[$accion] ?? ucfirst($accion)),
                        'categoria' => $grupo['label'],
                        'orden' => $orden,
                    ];
                }
            }
        }

        return $filas;
    }

    /**
     * Árbol para la UI de permisos de usuario.
     *
     * @return list<array{label: string, items: list<array{label: string, base: string, acciones: list<string>, permisos: list<array{codigo: string, accion: string, etiqueta: string}>}>}>
     */
    public static function arbolParaUi(): array
    {
        $arbol = [];
        foreach (self::grupos() as $grupo) {
            $items = [];
            foreach ($grupo['items'] as $item) {
                $permisos = [];
                foreach ($item['acciones'] as $accion) {
                    $permisos[] = [
                        'codigo' => self::codigo($item['base'], $accion),
                        'accion' => $accion,
                        'etiqueta' => self::etiquetasAccion()[$accion] ?? ucfirst($accion),
                    ];
                }
                $items[] = [
                    'label' => $item['label'],
                    'base' => $item['base'],
                    'acciones' => $item['acciones'],
                    'permisos' => $permisos,
                ];
            }
            $arbol[] = [
                'label' => $grupo['label'],
                'items' => $items,
            ];
        }

        return $arbol;
    }

    /**
     * Si el middleware pide un permiso legacy, acepta cualquiera de los granulares listados.
     *
     * @return list<string>
     */
    public static function compatiblesCon(string $permiso): array
    {
        $map = config('permisos.compat_any', []);

        return $map[$permiso] ?? [];
    }

    /**
     * Convierte permisos legacy almacenados en JSON a códigos granulares.
     *
     * @param  list<string>  $permisosActuales
     * @return list<string>
     */
    public static function migrarPermisos(array $permisosActuales): array
    {
        $map = config('permisos.migracion_legacy', []);
        $validos = array_flip(self::todosCodigos());
        $resultado = [];

        foreach ($permisosActuales as $codigo) {
            if (isset($validos[$codigo])) {
                $resultado[] = $codigo;
                continue;
            }
            if (isset($map[$codigo])) {
                foreach ($map[$codigo] as $nuevo) {
                    if (isset($validos[$nuevo])) {
                        $resultado[] = $nuevo;
                    }
                }
                continue;
            }
            // Mantener códigos desconocidos por si acaso (p. ej. personalizados)
            $resultado[] = $codigo;
        }

        return array_values(array_unique($resultado));
    }
}
