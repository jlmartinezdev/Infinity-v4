<?php

namespace App\Support;

use Illuminate\Http\Request;
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

    public static function borrar(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
