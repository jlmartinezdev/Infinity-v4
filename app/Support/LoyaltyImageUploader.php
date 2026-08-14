<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LoyaltyImageUploader
{
    public static function guardar(Request $request, string $carpeta, ?string $actual = null, string $field = 'imagen'): ?string
    {
        if ($request->boolean('eliminar_imagen') && $actual) {
            self::borrar($actual);

            return null;
        }

        if ($request->hasFile($field)) {
            self::borrar($actual);

            return $request->file($field)->store($carpeta, 'public');
        }

        return $actual;
    }

    /**
     * Guarda o elimina un archivo concreto (útil para campos anidados).
     */
    public static function guardarArchivo(?UploadedFile $file, string $carpeta, ?string $actual = null, bool $eliminar = false): ?string
    {
        if ($eliminar) {
            self::borrar($actual);

            return null;
        }

        if ($file) {
            self::borrar($actual);

            return $file->store($carpeta, 'public');
        }

        return $actual;
    }

    public static function urlPublica(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return url(Storage::disk('public')->url($path));
    }

    public static function borrar(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
