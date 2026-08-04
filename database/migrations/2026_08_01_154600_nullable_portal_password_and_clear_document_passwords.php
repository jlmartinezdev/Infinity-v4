<?php

use App\Services\ClientePortalUserService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY contrasena VARCHAR(255) NULL');

        // Quitar contraseñas = documento; conservar PLUS u otras ya otorgadas.
        app(ClientePortalUserService::class)->limpiarContrasenasDocumentoLegacy();
    }

    public function down(): void
    {
        // No restauramos contraseñas documento. Solo volvemos NOT NULL con string vacío.
        DB::table('users')->whereNull('contrasena')->update(['contrasena' => '']);
        DB::statement('ALTER TABLE users MODIFY contrasena VARCHAR(255) NOT NULL');
    }
};
