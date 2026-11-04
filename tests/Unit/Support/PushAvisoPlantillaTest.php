<?php

namespace Tests\Unit\Support;

use App\Models\PushAviso;
use App\Models\PushAvisoEntrega;
use App\Support\PushAvisoPlantilla;
use Tests\TestCase;

class PushAvisoPlantillaTest extends TestCase
{
    public function test_reemplaza_variables_sin_pisar_el_token_mas_largo(): void
    {
        $texto = 'Hola [nombre_completo], saldo Gs. [saldo_pend]. Vence [vencimiento].';

        $out = PushAvisoPlantilla::aplicar($texto, [
            'nombre' => 'Juan',
            'nombre_completo' => 'Juan Pérez',
            'saldo_pend' => '150.000',
            'vencimiento' => '15/11/2026',
        ]);

        $this->assertSame('Hola Juan Pérez, saldo Gs. 150.000. Vence 15/11/2026.', $out);
    }

    public function test_acepta_mayusculas_y_deja_vacio_lo_que_falta(): void
    {
        $out = PushAvisoPlantilla::aplicar('Hola [NOMBRE] [apellido]', [
            'nombre' => 'Ana',
        ]);

        $this->assertSame('Hola Ana ', $out);
    }

    public function test_etiqueta_de_grupo_por_vencer(): void
    {
        $aviso = new PushAviso([
            'destino' => 'por_vencer',
            'dias' => 1,
        ]);

        $this->assertSame('Por vencer (1 día)', $aviso->etiquetaDestino());
    }

    public function test_motivo_de_token_vencido_se_lee_en_claro(): void
    {
        $texto = PushAvisoEntrega::motivoLegible('UNREGISTERED', 'fallido');

        $this->assertStringContainsString('token', $texto);
    }

    public function test_etiqueta_de_saldo_pendiente(): void
    {
        $aviso = new PushAviso([
            'destino' => 'pendiente_pago',
        ]);

        $this->assertSame('Con saldo pendiente', $aviso->etiquetaDestino());
    }
}
