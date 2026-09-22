<?php

namespace Tests\Unit\Services;

use App\Services\DatabaseBackupService;
use App\Support\BackupScheduleConfig;
use Tests\TestCase;

class DatabaseBackupServiceTest extends TestCase
{
    public function test_esencial_omite_logs_y_completo_no(): void
    {
        $service = app(DatabaseBackupService::class);
        $omitir = $service->tablasEsencialOmitir();

        $this->assertContains('auditoria', $omitir);
        $this->assertContains('notifications', $omitir);
        $this->assertContains('servicio_conexion_eventos', $omitir);
        $this->assertContains('whatsapp_mensajes', $omitir);

        $esencial = $service->mysqlDumpCommand([
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'infinity',
            'username' => 'root',
            'password' => '',
        ], BackupScheduleConfig::TIPO_ESENCIAL);

        $this->assertContains('--ignore-table=infinity.auditoria', $esencial);
        $this->assertContains('--ignore-table=infinity.whatsapp_mensajes', $esencial);
        $this->assertContains('infinity', $esencial);

        $completo = $service->mysqlDumpCommand([
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'infinity',
            'username' => 'root',
            'password' => '',
        ], BackupScheduleConfig::TIPO_COMPLETO);

        $this->assertFalse(collect($completo)->contains(fn ($arg) => str_starts_with((string) $arg, '--ignore-table=')));
    }

    public function test_nombre_incluye_el_tipo(): void
    {
        $service = app(DatabaseBackupService::class);

        $this->assertStringContainsString('-esencial-', $service->suggestedFilename('esencial'));
        $this->assertStringContainsString('-completo-', $service->suggestedFilename('completo'));
    }

    public function test_tipo_invalido_cae_en_esencial(): void
    {
        $this->assertSame('esencial', BackupScheduleConfig::normalizarTipo(''));
        $this->assertSame('completo', BackupScheduleConfig::normalizarTipo('COMPLETO'));
    }
}
