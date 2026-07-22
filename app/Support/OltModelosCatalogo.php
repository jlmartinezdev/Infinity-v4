<?php

namespace App\Support;

use App\Models\OltModelo;
use Illuminate\Support\Facades\Schema;

class OltModelosCatalogo
{
    public static function usaBaseDeDatos(): bool
    {
        return Schema::hasTable('olt_modelos') && OltModelo::query()->exists();
    }

    /**
     * @return array<string, array{nombre: string, marca: string, imagen: string, descripcion: string, slug: string, imagen_url: string}>
     */
    public static function todos(): array
    {
        if (! self::usaBaseDeDatos()) {
            return [];
        }

        return OltModelo::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('marca')
            ->orderBy('nombre')
            ->get()
            ->mapWithKeys(fn (OltModelo $m) => [$m->slug => $m->toCatalogoArray()])
            ->all();
    }

    /**
     * @return array<string, array{nombre: string, marca: string, imagen: string, descripcion: string, slug: string, imagen_url: string}>
     */
    public static function listado(): array
    {
        if (! self::usaBaseDeDatos()) {
            return [];
        }

        return OltModelo::query()
            ->orderBy('orden')
            ->orderBy('marca')
            ->orderBy('nombre')
            ->get()
            ->mapWithKeys(fn (OltModelo $m) => [$m->slug => $m->toCatalogoArray()])
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function porMarca(bool $soloActivos = true): array
    {
        $items = $soloActivos ? self::todos() : self::listado();
        $grupos = [];

        foreach ($items as $slug => $data) {
            $marca = $data['marca'] ?? 'Otro';
            $grupos[$marca][$slug] = array_merge($data, ['slug' => $slug]);
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

        if (! self::usaBaseDeDatos()) {
            return null;
        }

        $modelo = OltModelo::query()->where('slug', $slug)->first();

        return $modelo ? $modelo->toCatalogoArray() : null;
    }

    public static function imagenUrl(?string $slug): string
    {
        return self::find($slug)['imagen_url'] ?? asset('images/olts/olt-generic.svg');
    }

    public static function nombre(?string $slug): ?string
    {
        return self::find($slug)['nombre'] ?? $slug;
    }

    /**
     * @return array<int, string>
     */
    public static function slugsValidos(bool $soloActivos = true): array
    {
        if (! self::usaBaseDeDatos()) {
            return [];
        }

        $q = OltModelo::query();
        if ($soloActivos) {
            $q->where('activo', true);
        }

        return $q->pluck('slug')->all();
    }
}
