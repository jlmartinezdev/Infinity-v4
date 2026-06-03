<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use App\Support\PermisosCatalogo;
use Illuminate\Database\Seeder;

class PermisoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermisosCatalogo::filasParaSeeder() as $p) {
            Permiso::updateOrCreate(['codigo' => $p['codigo']], $p);
        }

        $admin = Rol::whereRaw('LOWER(descripcion) = ?', ['administrador'])->first();
        $tecnico = Rol::whereRaw('LOWER(descripcion) = ?', ['técnico'])->first();
        $cajero = Rol::whereRaw('LOWER(descripcion) = ?', ['cajero'])->first();

        $todosPermisos = Permiso::pluck('id')->toArray();

        if ($admin) {
            $admin->permisos()->sync($todosPermisos);
        }

        $permisosTecnico = Permiso::whereIn('codigo', [
            'inicio.ver',
            'tareas.ver', 'tareas.crear', 'tareas.editar',
            'configuracion-ajustes.ver',
            'clientes-dashboard.ver', 'clientes-lista.ver', 'clientes-lista.crear', 'clientes-lista.editar',
            'clientes-pedidos.ver', 'clientes-pedidos.crear', 'clientes-pedidos.editar',
            'clientes-agenda.ver', 'clientes-agenda.crear', 'clientes-agenda.editar', 'clientes-agenda.eliminar',
            'clientes-mapa-pedidos.ver', 'clientes-mapa-pedidos.editar',
            'servicios-lista.ver', 'servicios-lista.crear', 'servicios-lista.editar',
            'servicios-hotspot.ver', 'servicios-hotspot-usuarios.ver', 'servicios-hotspot-perfiles.ver',
            'tv.ver', 'tv.crear', 'tv.editar', 'tv.eliminar',
            'tickets.ver', 'tickets.crear', 'tickets.editar',
            'planes.ver',
            'tipos-tecnologia.ver', 'perfiles-pppoe.ver', 'nodos.ver', 'ticket-asuntos.ver',
            'facturacion-dashboard.ver',
            'factura-interna.ver', 'cobros.ver', 'cobros-servicios.ver',
            'inventario-productos.ver', 'inventario-compras.ver', 'inventario-ventas.ver', 'inventario-gastos.ver',
            'inventario-productos.crear', 'inventario-productos.editar',
            'inventario-compras.crear', 'inventario-compras.editar',
            'inventario-ventas.crear', 'inventario-ventas.editar',
            'inventario-gastos.crear', 'inventario-gastos.editar',
        ])->pluck('id')->toArray();
        if ($tecnico) {
            $tecnico->permisos()->sync($permisosTecnico);
        }

        $permisosCajero = Permiso::whereIn('codigo', [
            'inicio.ver',
            'tareas.ver', 'tareas.crear',
            'configuracion-ajustes.ver',
            'clientes-lista.ver', 'clientes-pedidos.ver',
            'facturacion-dashboard.ver',
            'facturas.ver', 'facturas.crear', 'facturas.editar',
            'factura-interna.ver', 'factura-interna.crear', 'factura-interna.editar',
            'pagos-pendientes.ver', 'promesas-pago.ver',
            'cobros.ver', 'cobros.crear', 'cobros-servicios.ver', 'cobros-servicios.crear',
            'tickets.ver', 'tickets.crear', 'tickets.editar',
            'planes.ver',
        ])->pluck('id')->toArray();
        if ($cajero) {
            $cajero->permisos()->sync($permisosCajero);
        }
    }
}
