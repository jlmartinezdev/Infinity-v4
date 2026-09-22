<?php

namespace Tests\Unit\Models;

use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\ServicioHotspot;
use Tests\TestCase;

class ServicioHotspotTest extends TestCase
{
    public function test_maximo_de_tres_por_cliente(): void
    {
        $this->assertSame(3, ServicioHotspot::MAX_POR_CLIENTE);
        $this->assertSame([1, 2, 3], ServicioHotspot::slotsDisponibles());
    }

    public function test_slots_libres_omite_ocupados(): void
    {
        $this->assertSame([2, 3], ServicioHotspot::slotsLibresDe([1]));
        $this->assertSame([], ServicioHotspot::slotsLibresDe([1, 2, 3]));
    }

    public function test_fillable_incluye_cliente_y_slot(): void
    {
        $fillable = (new ServicioHotspot)->getFillable();
        $this->assertContains('cliente_id', $fillable);
        $this->assertContains('slot_numero', $fillable);
    }

    public function test_relaciones_has_many(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            (new Cliente)->servicioHotspots()
        );
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            (new Servicio)->servicioHotspots()
        );
    }

    public function test_username_usa_documento_y_sufijo_por_slot(): void
    {
        $cliente = new Cliente(['cedula' => '1.234.567']);
        $this->assertSame('1234567', ServicioHotspot::usernameDesdeDocumento($cliente, 1));
        $this->assertSame('1234567-2', ServicioHotspot::usernameDesdeDocumento($cliente, 2));
        $this->assertSame('1234567-3', ServicioHotspot::usernameDesdeDocumento($cliente, 3));
    }

    public function test_username_sin_cedula_es_nulo(): void
    {
        $this->assertNull(ServicioHotspot::usernameDesdeDocumento(new Cliente(['cedula' => '']), 1));
    }

    public function test_password_tiene_cuatro_digitos(): void
    {
        $clave = ServicioHotspot::generarPassword();
        $this->assertSame(ServicioHotspot::PIN_DIGITOS, 4);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $clave);
        $this->assertGreaterThanOrEqual(1000, (int) $clave);
        $this->assertLessThanOrEqual(9999, (int) $clave);
    }

    public function test_pin_oculto_sigue_el_largo_real(): void
    {
        $this->assertSame('••••', ServicioHotspot::pinOculto());
        $this->assertSame('••••', ServicioHotspot::pinOculto('4821'));
        $this->assertSame('••••••', ServicioHotspot::pinOculto('123456'));
    }

    public function test_reglas_pin_piden_cuatro_digitos(): void
    {
        $this->assertSame(['nullable', 'digits:4'], ServicioHotspot::reglasPin());
        $this->assertSame(['required', 'digits:4'], ServicioHotspot::reglasPin(true));
        $this->assertSame('El PIN tiene que tener 4 números.', ServicioHotspot::mensajePin());
    }

    public function test_recordar_cliente_pone_primero_y_omite_cero(): void
    {
        ServicioHotspot::recordarCliente(0);
        $this->assertSame([], session(ServicioHotspot::SESSION_RECIENTES, []));

        ServicioHotspot::recordarCliente(10);
        ServicioHotspot::recordarCliente(20);
        ServicioHotspot::recordarCliente(10);

        $this->assertSame([10, 20], session(ServicioHotspot::SESSION_RECIENTES));
    }

    public function test_ids_recientes_usa_sesion_llena_sin_consultar_tabla(): void
    {
        foreach (range(1, ServicioHotspot::RECIENTES_MAX) as $id) {
            ServicioHotspot::recordarCliente($id);
        }

        $this->assertSame(
            array_reverse(range(1, ServicioHotspot::RECIENTES_MAX)),
            ServicioHotspot::idsRecientes()
        );
    }
}
