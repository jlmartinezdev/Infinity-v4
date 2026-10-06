<?php

namespace Tests\Unit\Services;

use App\Models\Cliente;
use App\Services\ClienteContratoService;
use Tests\TestCase;

class ClienteContratoServiceTest extends TestCase
{
    public function test_nombre_documento_y_monto(): void
    {
        $cliente = new Cliente;
        $cliente->cliente_id = 12;
        $cliente->nombre = 'Ana';
        $cliente->apellido = 'Benítez';
        $cliente->cedula = '1.234.567';

        $this->assertSame('Ana Benítez', ClienteContratoService::nombreCliente($cliente));
        $this->assertSame('1.234.567', ClienteContratoService::documentoCliente($cliente));
        $this->assertSame('150.000 Gs.', ClienteContratoService::formatearGs(150000));
    }

    public function test_nombre_sin_datos_usa_id(): void
    {
        $cliente = new Cliente;
        $cliente->cliente_id = 8;
        $cliente->nombre = '';
        $cliente->apellido = null;

        $this->assertSame('Cliente #8', ClienteContratoService::nombreCliente($cliente));
        $this->assertSame('—', ClienteContratoService::documentoCliente($cliente));
    }
}
