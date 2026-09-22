<?php

namespace Tests\Unit\Support;

use Tests\TestCase;

class DatabaseDefaultConnectionTest extends TestCase
{
    public function test_si_falta_env_no_cae_a_sqlite(): void
    {
        $src = (string) file_get_contents(config_path('database.php'));

        $this->assertStringContainsString("env('DB_CONNECTION', 'mariadb')", $src);
        $this->assertStringNotContainsString("env('DB_CONNECTION', 'sqlite')", $src);
    }

    public function test_index_y_artisan_reintentan_leer_env(): void
    {
        $index = (string) file_get_contents(public_path('index.php'));
        $artisan = (string) file_get_contents(base_path('artisan'));

        $this->assertStringContainsString('bootstrap/ensure-env.php', $index);
        $this->assertStringContainsString('bootstrap/ensure-env.php', $artisan);
        $this->assertFileExists(base_path('bootstrap/ensure-env.php'));
    }
}
