<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrabajoRedFormTest extends TestCase
{
    public function test_actualizar_no_anida_el_formulario_de_eliminar(): void
    {
        $form = file_get_contents(resource_path('views/trabajos-red/_form.blade.php'));
        $edit = file_get_contents(resource_path('views/trabajos-red/edit.blade.php'));

        $this->assertIsString($form);
        $this->assertIsString($edit);
        $this->assertStringNotContainsString('id="trabajo-eliminar-form"', $form ?: '');
        $this->assertStringContainsString('form="trabajo-eliminar-form"', $form ?: '');

        $cierraActualizar = strpos($edit ?: '', '</form>');
        $abreEliminar = strpos($edit ?: '', 'id="trabajo-eliminar-form"');
        $this->assertNotFalse($cierraActualizar);
        $this->assertNotFalse($abreEliminar);
        $this->assertLessThan($abreEliminar, $cierraActualizar);
        $this->assertStringContainsString("@method('DELETE')", $edit ?: '');
    }
}
