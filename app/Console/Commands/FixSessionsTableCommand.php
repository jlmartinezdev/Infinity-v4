<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixSessionsTableCommand extends Command
{
    protected $signature = 'sessions:fix';

    protected $description = 'Agrega la columna user_id a la tabla sessions si no existe (requerido por Laravel 11+)';

    public function handle(): int
    {
        if (! Schema::hasTable('sessions')) {
            $this->error('La tabla sessions no existe.');

            return 1;
        }

        if (Schema::hasColumn('sessions', 'user_id')) {
            $this->info('La columna user_id ya existe en la tabla sessions.');

            return 0;
        }

        try {
            DB::statement('ALTER TABLE sessions ADD COLUMN user_id INT UNSIGNED NULL AFTER id');
            DB::statement('ALTER TABLE sessions ADD INDEX sessions_user_id_index (user_id)');

            if (Schema::hasTable('users') && Schema::hasColumn('users', 'usuario_id')) {
                DB::statement('ALTER TABLE sessions ADD CONSTRAINT sessions_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(usuario_id) ON DELETE CASCADE');
            }

            $this->info('Columna user_id agregada correctamente a la tabla sessions.');
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());

            return 1;
        }

        return 0;
    }
}
