<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class PermisoSeeder extends Seeder
{
    /**
     * Permisos del sistema y asignación por defecto a roles.
     */
    public function run(): void
    {
        $permisos = [
            ['codigo' => 'dashboard.ver', 'nombre' => 'Ver Dashboard', 'categoria' => 'General', 'orden' => 1],
            ['codigo' => 'tareas.ver', 'nombre' => 'Ver dashboard de tareas', 'categoria' => 'General', 'orden' => 3],
            ['codigo' => 'tareas.crear', 'nombre' => 'Crear/editar tareas', 'categoria' => 'Tareas', 'orden' => 4],
            ['codigo' => 'tareas.eliminar', 'nombre' => 'Eliminar tareas', 'categoria' => 'Tareas', 'orden' => 5],
            ['codigo' => 'clientes.ver', 'nombre' => 'Ver lista de clientes', 'categoria' => 'Clientes', 'orden' => 10],
            ['codigo' => 'clientes.crear', 'nombre' => 'Crear clientes', 'categoria' => 'Clientes', 'orden' => 11],
            ['codigo' => 'clientes.editar', 'nombre' => 'Editar clientes', 'categoria' => 'Clientes', 'orden' => 12],
            ['codigo' => 'clientes.eliminar', 'nombre' => 'Eliminar clientes', 'categoria' => 'Clientes', 'orden' => 13],
            ['codigo' => 'pedidos.ver', 'nombre' => 'Ver pedidos', 'categoria' => 'Pedidos', 'orden' => 20],
            ['codigo' => 'pedidos.ver-todos', 'nombre' => 'Ver todos los pedidos', 'categoria' => 'Pedidos', 'orden' => 21],
            ['codigo' => 'pedidos.crear', 'nombre' => 'Crear pedidos', 'categoria' => 'Pedidos', 'orden' => 22],
            ['codigo' => 'pedidos.editar', 'nombre' => 'Editar pedidos', 'categoria' => 'Pedidos', 'orden' => 23],
            ['codigo' => 'pedidos.eliminar', 'nombre' => 'Eliminar pedidos', 'categoria' => 'Pedidos', 'orden' => 24],
            ['codigo' => 'pedidos.finalizar', 'nombre' => 'Finalizar pedidos (instalado)', 'categoria' => 'Pedidos', 'orden' => 25],
            ['codigo' => 'agenda.ver', 'nombre' => 'Ver agenda', 'categoria' => 'Agenda', 'orden' => 30],
            ['codigo' => 'agenda.crear', 'nombre' => 'Crear/editar agenda', 'categoria' => 'Agenda', 'orden' => 31],
            ['codigo' => 'agenda.eliminar', 'nombre' => 'Eliminar citas agenda', 'categoria' => 'Agenda', 'orden' => 32],
            ['codigo' => 'servicios.ver', 'nombre' => 'Ver servicios', 'categoria' => 'Servicios', 'orden' => 40],
            ['codigo' => 'servicios.crear', 'nombre' => 'Crear/editar servicios', 'categoria' => 'Servicios', 'orden' => 41],
            ['codigo' => 'servicios.eliminar', 'nombre' => 'Eliminar servicios', 'categoria' => 'Servicios', 'orden' => 42],
            ['codigo' => 'tv.ver', 'nombre' => 'Ver cuentas TV (streaming)', 'categoria' => 'Servicios', 'orden' => 43],
            ['codigo' => 'tv.editar', 'nombre' => 'Gestionar cuentas TV y asignaciones', 'categoria' => 'Servicios', 'orden' => 44],
            ['codigo' => 'facturacion.ver', 'nombre' => 'Ver menú facturación', 'categoria' => 'Facturación', 'orden' => 49],
            ['codigo' => 'facturas.ver', 'nombre' => 'Ver facturas electrónicas', 'categoria' => 'Facturación', 'orden' => 50],
            ['codigo' => 'facturas.crear', 'nombre' => 'Crear/editar facturas electrónicas', 'categoria' => 'Facturación', 'orden' => 51],
            ['codigo' => 'facturas.eliminar', 'nombre' => 'Eliminar facturas', 'categoria' => 'Facturación', 'orden' => 52],
            ['codigo' => 'factura-interna.ver', 'nombre' => 'Ver facturas internas', 'categoria' => 'Facturación', 'orden' => 53],
            ['codigo' => 'factura-interna.crear', 'nombre' => 'Crear/editar facturas internas', 'categoria' => 'Facturación', 'orden' => 54],
            ['codigo' => 'factura-interna.eliminar', 'nombre' => 'Eliminar facturas internas', 'categoria' => 'Facturación', 'orden' => 55],
            ['codigo' => 'pagos-pendientes.ver', 'nombre' => 'Ver pagos pendientes', 'categoria' => 'Facturación', 'orden' => 56],
            ['codigo' => 'cobros.ver', 'nombre' => 'Ver cobros y recibos', 'categoria' => 'Facturación', 'orden' => 57],
            ['codigo' => 'cobros.crear', 'nombre' => 'Registrar cobros', 'categoria' => 'Facturación', 'orden' => 58],
            ['codigo' => 'cobros.eliminar', 'nombre' => 'Eliminar cobros', 'categoria' => 'Facturación', 'orden' => 59],
            ['codigo' => 'tickets.ver', 'nombre' => 'Ver tickets', 'categoria' => 'Tickets', 'orden' => 60],
            ['codigo' => 'tickets.crear', 'nombre' => 'Crear/editar tickets', 'categoria' => 'Tickets', 'orden' => 61],
            ['codigo' => 'tickets.eliminar', 'nombre' => 'Eliminar tickets', 'categoria' => 'Tickets', 'orden' => 62],
            ['codigo' => 'planes.ver', 'nombre' => 'Ver planes', 'categoria' => 'Referenciales', 'orden' => 70],
            ['codigo' => 'planes.crear', 'nombre' => 'Crear/editar planes', 'categoria' => 'Referenciales', 'orden' => 71],
            ['codigo' => 'planes.eliminar', 'nombre' => 'Eliminar planes', 'categoria' => 'Referenciales', 'orden' => 72],
            ['codigo' => 'referenciales.ver', 'nombre' => 'Ver referenciales (nodos, PPPoE, etc.)', 'categoria' => 'Referenciales', 'orden' => 73],
            ['codigo' => 'referenciales.editar', 'nombre' => 'Editar referenciales', 'categoria' => 'Referenciales', 'orden' => 74],
            ['codigo' => 'usuarios.ver', 'nombre' => 'Ver usuarios', 'categoria' => 'Usuarios', 'orden' => 80],
            ['codigo' => 'usuarios.crear', 'nombre' => 'Crear usuarios', 'categoria' => 'Usuarios', 'orden' => 81],
            ['codigo' => 'usuarios.editar', 'nombre' => 'Editar usuarios', 'categoria' => 'Usuarios', 'orden' => 82],
            ['codigo' => 'usuarios.eliminar', 'nombre' => 'Eliminar usuarios', 'categoria' => 'Usuarios', 'orden' => 83],
            ['codigo' => 'usuarios.permisos', 'nombre' => 'Gestionar permisos', 'categoria' => 'Usuarios', 'orden' => 84],
            ['codigo' => 'sistema.ver', 'nombre' => 'Ver sistema (auditoría, routers, etc.)', 'categoria' => 'Sistema', 'orden' => 90],
            ['codigo' => 'sistema.editar', 'nombre' => 'Editar configuración sistema', 'categoria' => 'Sistema', 'orden' => 91],
            ['codigo' => 'configuracion.ver', 'nombre' => 'Ver configuración', 'categoria' => 'General', 'orden' => 2],
            ['codigo' => 'inventario.ver', 'nombre' => 'Ver inventario, compras, ventas, gastos', 'categoria' => 'Inventario', 'orden' => 45],
            ['codigo' => 'inventario.editar', 'nombre' => 'Gestionar inventario, compras, ventas, gastos, pagos', 'categoria' => 'Inventario', 'orden' => 46],
        ];

        foreach ($permisos as $p) {
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
            'dashboard.ver', 'tareas.ver', 'tareas.crear', 'configuracion.ver', 'clientes.ver', 'clientes.crear', 'clientes.editar',
            'pedidos.ver', 'pedidos.crear', 'pedidos.editar', 'pedidos.finalizar',
            'agenda.ver', 'agenda.crear', 'agenda.eliminar',
            'servicios.ver', 'servicios.crear', 'tv.ver', 'tv.editar',
            'tickets.ver', 'tickets.crear',
            'planes.ver', 'referenciales.ver',
            'facturacion.ver', 'factura-interna.ver', 'cobros.ver',
            'inventario.ver', 'inventario.editar',
        ])->pluck('id')->toArray();
        if ($tecnico) {
            $tecnico->permisos()->sync($permisosTecnico);
        }

        $permisosCajero = Permiso::whereIn('codigo', [
            'dashboard.ver', 'tareas.ver', 'tareas.crear', 'configuracion.ver', 'clientes.ver', 'pedidos.ver',
            'facturacion.ver', 'facturas.ver', 'facturas.crear',
            'factura-interna.ver', 'factura-interna.crear',
            'pagos-pendientes.ver', 'cobros.ver', 'cobros.crear',
            'tickets.ver', 'tickets.crear', 'planes.ver',
        ])->pluck('id')->toArray();
        if ($cajero) {
            $cajero->permisos()->sync($permisosCajero);
        }
    }
}
