<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class InventarioService
{
    /**
     * Registrar entrada de inventario por compra.
     */
    public function registrarEntradaCompra(Compra $compra): void
    {
        foreach ($compra->detalles as $detalle) {
            $producto = $detalle->producto;
            $stockAnterior = (float) $producto->stock_actual;
            $cantidad = (float) $detalle->cantidad;
            $stockNuevo = $stockAnterior + $cantidad;

            $producto->update(['stock_actual' => $stockNuevo]);

            InventarioMovimiento::create([
                'producto_id' => $producto->id,
                'tipo' => 'entrada',
                'cantidad' => $cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'referencia_tipo' => 'compra',
                'referencia_id' => $compra->id,
                'motivo' => $compra->numero_factura ? "Compra #{$compra->id} - {$compra->numero_factura}" : "Compra #{$compra->id}",
                'usuario_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Registrar salida de inventario por venta.
     */
    public function registrarSalidaVenta(Venta $venta): void
    {
        foreach ($venta->detalles as $detalle) {
            $producto = $detalle->producto;
            $stockAnterior = (float) $producto->stock_actual;
            $cantidad = (float) $detalle->cantidad;
            $stockNuevo = max(0, $stockAnterior - $cantidad);

            $producto->update(['stock_actual' => $stockNuevo]);

            InventarioMovimiento::create([
                'producto_id' => $producto->id,
                'tipo' => 'salida',
                'cantidad' => -$cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'referencia_tipo' => 'venta',
                'referencia_id' => $venta->id,
                'motivo' => "Venta #{$venta->id}",
                'usuario_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Ajuste manual de inventario.
     */
    public function ajustar(Producto $producto, float $cantidad, string $motivo = ''): void
    {
        $stockAnterior = (float) $producto->stock_actual;
        $stockNuevo = max(0, $stockAnterior + $cantidad);
        $tipo = $cantidad >= 0 ? 'ajuste_entrada' : 'ajuste_salida';

        $producto->update(['stock_actual' => $stockNuevo]);

        InventarioMovimiento::create([
            'producto_id' => $producto->id,
            'tipo' => 'ajuste',
            'cantidad' => $cantidad,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo' => $stockNuevo,
            'referencia_tipo' => 'ajuste',
            'motivo' => $motivo ?: 'Ajuste manual',
            'usuario_id' => auth()->id(),
        ]);
    }

    /**
     * Revertir inventario de una compra (anulación).
     */
    public function revertirCompra(Compra $compra): void
    {
        foreach ($compra->detalles as $detalle) {
            $producto = $detalle->producto;
            $stockAnterior = (float) $producto->stock_actual;
            $cantidad = (float) $detalle->cantidad;
            $stockNuevo = max(0, $stockAnterior - $cantidad);

            $producto->update(['stock_actual' => $stockNuevo]);

            InventarioMovimiento::create([
                'producto_id' => $producto->id,
                'tipo' => 'salida',
                'cantidad' => -$cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'referencia_tipo' => 'compra_anulada',
                'referencia_id' => $compra->id,
                'motivo' => "Anulación compra #{$compra->id}",
                'usuario_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Revertir inventario de una venta (anulación).
     */
    public function revertirVenta(Venta $venta): void
    {
        foreach ($venta->detalles as $detalle) {
            $producto = $detalle->producto;
            $stockAnterior = (float) $producto->stock_actual;
            $cantidad = (float) $detalle->cantidad;
            $stockNuevo = $stockAnterior + $cantidad;

            $producto->update(['stock_actual' => $stockNuevo]);

            InventarioMovimiento::create([
                'producto_id' => $producto->id,
                'tipo' => 'entrada',
                'cantidad' => $cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'referencia_tipo' => 'venta_anulada',
                'referencia_id' => $venta->id,
                'motivo' => "Anulación venta #{$venta->id}",
                'usuario_id' => auth()->id(),
            ]);
        }
    }
}
