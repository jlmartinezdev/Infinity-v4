<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tablas Maestras (Sin llaves foráneas)
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('rol_id');
            $table->string('descripcion', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('tipos_tecnologias', function (Blueprint $table) {
            $table->increments('tecnologia_id');
            $table->string('descripcion', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('perfiles_pppoe', function (Blueprint $table) {
            $table->increments('perfil_pppoe_id');
            $table->string('nombre', 150)->nullable();
            $table->string('local_address', 20)->nullable();
            $table->string('remote_address', 20)->nullable();
            $table->string('rate_limit_tx_rx', 30)->nullable();
        });

        Schema::create('estados_pedidos', function (Blueprint $table) {
            $table->increments('estado_id');
            $table->string('descripcion', 120)->nullable();
        });

        Schema::create('nodos', function (Blueprint $table) {
            $table->increments('nodo_id');
            $table->string('descripcion', 120)->nullable();
            $table->string('coordenas_gps', 50)->nullable();
            $table->string('ciudad', 50)->nullable();
            $table->timestamps();
        });

    

        // 2. Usuarios y Auditoría
        Schema::create('users', function (Blueprint $table) {
            $table->increments('usuario_id');
            $table->unsignedInteger('rol_id');
            $table->string('name', 255);
            $table->string('email', 255)->index();
            $table->string('contrasena', 255);
            $table->longText('permisos')->nullable();
            $table->enum('estado', ['activo', 'pendiente_aprobacion', 'suspendido'])->default('activo');
            $table->text('notas')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('rol_id')->references('rol_id')->on('roles');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
            
            $table->foreign('user_id')->references('usuario_id')->on('users')->onDelete('cascade');
        });
        Schema::create('auditoria', function (Blueprint $table) {
            $table->increments('auditoria_id');
            $table->unsignedInteger('usuario_id');
            $table->string('tabla', 100);
            $table->string('accion', 100);
            $table->integer('registro_id');
            $table->longText('detalles')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('usuario_id')->references('usuario_id')->on('users');
        });

        // 3. Infraestructura de Red
        Schema::create('routers', function (Blueprint $table) {
            $table->increments('router_id');
            $table->unsignedInteger('nodo_id');
            $table->string('nombre', 100);
            $table->string('ip', 64);
            $table->integer('api_port')->default(8728);
            $table->string('usuario', 64);
            $table->string('password', 128)->nullable();
            $table->string('estado', 32)->default('desconocido');
            $table->timestamps();

            $table->foreign('nodo_id')->references('nodo_id')->on('nodos');
        });

        Schema::create('router_ip_pools', function (Blueprint $table) {
            $table->increments('pool_id');
            $table->unsignedInteger('router_id');
            $table->string('ip_range', 64);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('router_id')->references('router_id')->on('routers');
        });

        Schema::create('pool_ip_asignadas', function (Blueprint $table) {
            $table->string('ip', 15);
            $table->unsignedInteger('pool_id');
            $table->enum('estado', ['disponible', 'asignada', 'reservada'])->default('disponible')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['ip', 'pool_id']);
            $table->foreign('pool_id')->references('pool_id')->on('router_ip_pools');
        });

        // 4. Comercial y Servicios
        Schema::create('planes', function (Blueprint $table) {
            $table->increments('plan_id');
            $table->unsignedInteger('perfil_pppoe_id');
            $table->unsignedInteger('tecnologia_id');
            $table->string('nombre', 100);
            $table->string('velocidad', 50);
            $table->decimal('precio', 10, 2);
            $table->text('descripcion')->nullable();
            $table->string('estado', 20)->default('activo')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('perfil_pppoe_id')->references('perfil_pppoe_id')->on('perfiles_pppoe');
            $table->foreign('tecnologia_id')->references('tecnologia_id')->on('tipos_tecnologias');
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->increments('cliente_id');
            $table->string('cedula', 20);
            $table->string('nombre', 100);
            $table->string('apellido', 100)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->text('direccion')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('servicios', function (Blueprint $table) {
            $table->unsignedInteger('cliente_id');
            $table->unsignedInteger('servicio_id');
            $table->unsignedInteger('pool_id');
            $table->unsignedInteger('plan_id');
            $table->string('ip', 15)->nullable();
            $table->string('usuario_pppoe', 100)->nullable();
            $table->string('password_ppoe', 20)->nullable();
            $table->date('fecha_instalacion')->nullable();
            $table->date('fecha_cancelacion')->nullable();
            $table->char('estado', 1)->nullable();
            $table->string('mac_address', 20)->nullable();
            $table->string('pppoe_status', 32)->nullable();
            $table->timestamp('pppoe_synced')->nullable();

            $table->primary(['cliente_id', 'servicio_id']);
            $table->foreign('cliente_id')->references('cliente_id')->on('clientes');
            $table->foreign('plan_id')->references('plan_id')->on('planes');
        });
    }

    public function down(): void
    {
        // Borrar en orden inverso para evitar errores de integridad
        Schema::dropIfExists('servicios');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('planes');
        Schema::dropIfExists('pool_ip_asignadas');
        Schema::dropIfExists('router_ip_pools');
        Schema::dropIfExists('routers');
        Schema::dropIfExists('auditoria');
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('nodos');
        Schema::dropIfExists('estados_pedidos');
        Schema::dropIfExists('perfiles_pppoe');
        Schema::dropIfExists('tipos_tecnologias');
        Schema::dropIfExists('roles');
    }
};