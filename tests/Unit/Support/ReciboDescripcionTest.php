<?php

namespace Tests\Unit\Support;

use App\Models\Cobro;
use App\Support\ReciboDescripcion;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ReciboDescripcionTest extends TestCase
{
    public function test_deja_el_nombre_del_plan_sin_periodo_ni_importe(): void
    {
        $linea = 'PLAN BASICO FTTH 100 Mbps - 20.000 Gs. - Desde 26/11/2026 hasta 30/11/2026';

        $this->assertSame('PLAN BASICO FTTH 100 Mbps', ReciboDescripcion::linea($linea));
    }

    public function test_la_app_de_tv_queda_como_servicio_especial(): void
    {
        $linea = 'Servicio especial - 20.000 Gs. - Período 01/11/2026 hasta 30/11/2026';

        $this->assertSame('Servicio especial', ReciboDescripcion::linea($linea));
    }

    public function test_no_recorta_un_servicio_especial_de_instalacion(): void
    {
        $this->assertSame(
            'Servicio especial — instalación',
            ReciboDescripcion::linea('Servicio especial — instalación')
        );
    }

    public function test_une_plan_y_tv_sin_repetir(): void
    {
        $texto = 'Casa · PLAN ESTANDAR 20 Mbps - 35.000 Gs. - Desde 25/11/2026 hasta 30/11/2026 | Servicio especial - 20.000 Gs. - Período 01/11/2026 hasta 30/11/2026';

        $this->assertSame(
            'PLAN ESTANDAR 20 Mbps / Servicio especial',
            ReciboDescripcion::texto($texto)
        );
    }

    public function test_el_pago_adelantado_usa_la_descripcion_corta(): void
    {
        $cobro = new Cobro([
            'concepto' => 'PLAN BASICO FTTH 100 Mbps - 100.000 Gs. - Desde 01/11/2026 hasta 30/11/2026 | Servicio especial - 20.000 Gs. - Período 01/11/2026 hasta 30/11/2026',
        ]);
        $cobro->setRelation('facturaInternas', new Collection);

        $this->assertTrue(ReciboDescripcion::esPagoAdelantado($cobro));
        $this->assertSame(
            'PLAN BASICO FTTH 100 Mbps / Servicio especial',
            ReciboDescripcion::paraCobro($cobro)
        );
    }

    public function test_el_cobro_de_factura_conserva_el_periodo(): void
    {
        $concepto = 'PLAN BASICO FTTH 100 Mbps - 100.000 Gs. - Desde 01/11/2026 hasta 30/11/2026';
        $cobro = new Cobro([
            'concepto' => $concepto,
            'factura_interna_id' => 15,
        ]);
        $cobro->setRelation('facturaInternas', new Collection([(object) ['id' => 15]]));

        $this->assertFalse(ReciboDescripcion::esPagoAdelantado($cobro));
        $this->assertSame($concepto, ReciboDescripcion::paraCobro($cobro));
    }
}
