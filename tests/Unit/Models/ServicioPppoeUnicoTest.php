<?php

namespace Tests\Unit\Models;

use App\Models\Cliente;
use App\Models\Servicio;
use Tests\TestCase;

class ServicioPppoeUnicoTest extends TestCase
{
    public function test_normaliza_ene_y_espacios(): void
    {
        $this->assertSame('NUNEZ', Servicio::normalizarFragmentoUsuarioPppoe('Núñez'));
        $this->assertSame('2DO_PISO', Servicio::normalizarFragmentoUsuarioPppoe('2do piso'));
    }

    public function test_base_desde_nombre_y_apellido(): void
    {
        $cliente = new Cliente;
        $cliente->cliente_id = 1373;
        $cliente->nombre = 'Damian';
        $cliente->apellido = 'Cantero Rios';

        $this->assertSame('DAMIAN_CANTERO_RIOS', Servicio::baseUsuarioPppoeDesdeCliente($cliente));
    }

    public function test_compone_usuario_con_alias_distinto(): void
    {
        $this->assertSame('DAMIAN_CANTERO_CASA', Servicio::componerUsuarioPppoe('DAMIAN_CANTERO', 'casa'));
        $this->assertSame('DAMIAN_CANTERO_CASA', Servicio::componerUsuarioPppoe('DAMIAN_CANTERO_CASA', 'casa'));
        $this->assertSame('DAMIAN_CANTERO', Servicio::componerUsuarioPppoe('DAMIAN_CANTERO', ''));
    }

    public function test_siguiente_libre_agrega_sufijo_si_esta_ocupado(): void
    {
        $this->assertSame(
            'DAMIAN_CANTERO_2',
            Servicio::siguienteUsuarioPppoeLibre('DAMIAN_CANTERO', ['DAMIAN_CANTERO'])
        );
        $this->assertSame(
            'DAMIAN_CANTERO_CASA',
            Servicio::siguienteUsuarioPppoeLibre('DAMIAN_CANTERO_CASA', ['DAMIAN_CANTERO'])
        );
    }

    public function test_password_distinta_a_las_existentes(): void
    {
        $evitar = ['AbCdEfGh'];
        $nueva = Servicio::generarPasswordPppoe($evitar);

        $this->assertSame(8, strlen($nueva));
        $this->assertNotSame('AbCdEfGh', $nueva);
    }
}
