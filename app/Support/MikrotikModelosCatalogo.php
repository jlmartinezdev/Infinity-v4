<?php

namespace App\Support;

use App\Models\RouterModelo;
use Illuminate\Support\Facades\Schema;

class MikrotikModelosCatalogo
{
    public static function usaBaseDeDatos(): bool
    {
        return Schema::hasTable('router_modelos') && RouterModelo::query()->exists();
    }

    /**
     * @return array<string, array{nombre: string, serie: string, imagen: string, descripcion: string, imagen_url?: string}>
     */
    public static function todos(): array
    {
        if (self::usaBaseDeDatos()) {
            return RouterModelo::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->orderBy('serie')
                ->orderBy('nombre')
                ->get()
                ->mapWithKeys(fn (RouterModelo $m) => [$m->slug => self::mapItem($m)])
                ->all();
        }

        return config('mikrotik_modelos', []);
    }

    /**
     * @return array<string, array{nombre: string, serie: string, imagen: string, descripcion: string, slug: string, imagen_url: string}>
     */
    public static function listado(): array
    {
        if (self::usaBaseDeDatos()) {
            return RouterModelo::query()
                ->orderBy('orden')
                ->orderBy('serie')
                ->orderBy('nombre')
                ->get()
                ->mapWithKeys(fn (RouterModelo $m) => [$m->slug => self::mapItem($m)])
                ->all();
        }

        $items = [];
        foreach (config('mikrotik_modelos', []) as $slug => $data) {
            $items[$slug] = array_merge($data, [
                'slug' => $slug,
                'imagen_url' => asset($data['imagen'] ?? 'images/routers/mikrotik-generic.svg'),
            ]);
        }

        return $items;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function porSerie(bool $soloActivos = true): array
    {
        $items = $soloActivos ? self::todos() : self::listado();
        $grupos = [];

        foreach ($items as $slug => $data) {
            $serie = $data['serie'] ?? 'Otro';
            $grupos[$serie][$slug] = array_merge($data, ['slug' => $slug]);
        }

        ksort($grupos);

        return $grupos;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(?string $slug): ?array
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        if (self::usaBaseDeDatos()) {
            $modelo = RouterModelo::query()->where('slug', $slug)->first();

            return $modelo ? self::mapItem($modelo) : null;
        }

        $data = config('mikrotik_modelos', [])[$slug] ?? null;
        if ($data === null) {
            return null;
        }

        return array_merge($data, [
            'slug' => $slug,
            'imagen_url' => asset($data['imagen'] ?? 'images/routers/mikrotik-generic.svg'),
        ]);
    }

    public static function imagenUrl(?string $slug): string
    {
        return self::find($slug)['imagen_url'] ?? asset('images/routers/mikrotik-generic.svg');
    }

    /**
     * @return array<int, string>
     */
    public static function slugsValidos(bool $soloActivos = true): array
    {
        if (self::usaBaseDeDatos()) {
            $q = RouterModelo::query();
            if ($soloActivos) {
                $q->where('activo', true);
            }

            return $q->pluck('slug')->all();
        }

        return array_keys(config('mikrotik_modelos', []));
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapItem(RouterModelo $modelo): array
    {
        return $modelo->toCatalogoArray();
    }
}
