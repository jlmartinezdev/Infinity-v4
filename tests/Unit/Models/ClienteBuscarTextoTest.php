<?php

namespace Tests\Unit\Models;

use App\Models\Cliente;
use Tests\TestCase;

class ClienteBuscarTextoTest extends TestCase
{
    public function test_una_palabra_busca_en_nombre_o_apellido(): void
    {
        $query = Cliente::query()->buscarTexto('dario');

        $this->assertStringContainsString('nombre', $query->toSql());
        $this->assertContains('%dario%', $query->getBindings());
        $this->assertNotContains('%v%', $query->getBindings());
    }

    public function test_varias_palabras_exigen_todas_en_el_nombre_completo(): void
    {
        $query = Cliente::query()->buscarTexto('dario v');
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertContains('%dario%', $bindings);
        $this->assertContains('%v%', $bindings);
        $this->assertSame(6, count(array_filter($bindings, static fn ($b) => $b === '%dario%')));
        $this->assertSame(6, count(array_filter($bindings, static fn ($b) => $b === '%v%')));
        $this->assertStringContainsString(' and ', strtolower($sql));
        $this->assertStringContainsString('concat', strtolower($sql));
    }

    public function test_escapa_comodines_like(): void
    {
        $bindings = Cliente::query()->buscarTexto('dario%')->getBindings();

        $this->assertContains('%dario\%%', $bindings);
        $this->assertNotContains('%dario%%', $bindings);
    }

    public function test_vacio_no_agrega_filtro(): void
    {
        $sql = Cliente::query()->buscarTexto('   ')->toSql();

        $this->assertSame(Cliente::query()->toSql(), $sql);
    }

    public function test_numero_con_numeral_busca_por_id(): void
    {
        $query = Cliente::query()->buscarTexto('#1666');
        $sql = strtolower($query->toSql());
        $bindings = $query->getBindings();

        $this->assertStringContainsString('cliente_id', $sql);
        $this->assertContains(1666, $bindings);
        $this->assertContains('%1666%', $bindings);
        $this->assertNotContains('%#1666%', $bindings);
    }

    public function test_tambien_busca_alias_de_servicio(): void
    {
        $sql = strtolower(Cliente::query()->buscarTexto('casa')->toSql());

        $this->assertStringContainsString('alias', $sql);
        $this->assertStringContainsString('servicios', $sql);
    }
}
