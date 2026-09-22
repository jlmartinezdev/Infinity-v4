<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function connectionName(): string
    {
        return (string) config('radius.connection', 'radius');
    }

    protected function puedeConectar(): bool
    {
        try {
            DB::connection($this->connectionName())->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (! $this->puedeConectar()) {
            if (config('radius.enabled')) {
                throw new RuntimeException('RADIUS_ENABLED=true pero no se pudo conectar a la base radius.');
            }

            return;
        }

        $schema = Schema::connection($this->connectionName());

        if (! $schema->hasTable('nas')) {
            $schema->create('nas', function (Blueprint $table) {
                $table->increments('id');
                $table->string('nasname', 128);
                $table->string('shortname', 32)->nullable();
                $table->string('type', 30)->default('other');
                $table->integer('ports')->nullable();
                $table->string('secret', 60)->default('secret');
                $table->string('server', 64)->nullable();
                $table->string('community', 50)->nullable();
                $table->string('description', 200)->default('RADIUS Client');
                $table->index('nasname');
            });
        }

        if (! $schema->hasTable('radcheck')) {
            $schema->create('radcheck', function (Blueprint $table) {
                $table->increments('id');
                $table->string('username', 64)->default('');
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default('==');
                $table->string('value', 253)->default('');
                $table->index('username');
            });
        }

        if (! $schema->hasTable('radreply')) {
            $schema->create('radreply', function (Blueprint $table) {
                $table->increments('id');
                $table->string('username', 64)->default('');
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default('=');
                $table->string('value', 253)->default('');
                $table->index('username');
            });
        }

        if (! $schema->hasTable('radusergroup')) {
            $schema->create('radusergroup', function (Blueprint $table) {
                $table->increments('id');
                $table->string('username', 64)->default('');
                $table->string('groupname', 64)->default('');
                $table->integer('priority')->default(1);
                $table->index('username');
            });
        }

        if (! $schema->hasTable('radgroupcheck')) {
            $schema->create('radgroupcheck', function (Blueprint $table) {
                $table->increments('id');
                $table->string('groupname', 64)->default('');
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default('==');
                $table->string('value', 253)->default('');
                $table->index('groupname');
            });
        }

        if (! $schema->hasTable('radgroupreply')) {
            $schema->create('radgroupreply', function (Blueprint $table) {
                $table->increments('id');
                $table->string('groupname', 64)->default('');
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default('=');
                $table->string('value', 253)->default('');
                $table->index('groupname');
            });
        }

        if (! $schema->hasTable('radacct')) {
            $schema->create('radacct', function (Blueprint $table) {
                $table->bigIncrements('radacctid');
                $table->string('acctsessionid', 64)->default('');
                $table->string('acctuniqueid', 32)->default('');
                $table->string('username', 64)->default('');
                $table->string('realm', 64)->nullable()->default('');
                $table->string('nasipaddress', 15)->default('');
                $table->string('nasportid', 32)->nullable();
                $table->string('nasporttype', 32)->nullable();
                $table->dateTime('acctstarttime')->nullable();
                $table->dateTime('acctupdatetime')->nullable();
                $table->dateTime('acctstoptime')->nullable();
                $table->integer('acctinterval')->nullable();
                $table->integer('acctsessiontime')->nullable();
                $table->string('acctauthentic', 32)->nullable();
                $table->string('connectinfo_start', 50)->nullable();
                $table->string('connectinfo_stop', 50)->nullable();
                $table->bigInteger('acctinputoctets')->nullable();
                $table->bigInteger('acctoutputoctets')->nullable();
                $table->string('calledstationid', 50)->default('');
                $table->string('callingstationid', 50)->default('');
                $table->string('acctterminatecause', 32)->default('');
                $table->string('servicetype', 32)->nullable();
                $table->string('framedprotocol', 32)->nullable();
                $table->string('framedipaddress', 15)->default('');
                $table->string('framedipv6address', 45)->default('');
                $table->string('framedipv6prefix', 45)->default('');
                $table->string('framedinterfaceid', 44)->default('');
                $table->string('delegatedipv6prefix', 45)->default('');
                $table->string('class', 64)->nullable();
                $table->unique('acctuniqueid');
                $table->index('username');
                $table->index('framedipaddress');
                $table->index('acctsessionid');
                $table->index('acctsessiontime');
                $table->index('acctstarttime');
                $table->index('acctinterval');
                $table->index('acctstoptime');
                $table->index('nasipaddress');
            });
        }

        if (! $schema->hasTable('nasreload')) {
            $schema->create('nasreload', function (Blueprint $table) {
                $table->string('nasipaddress', 15);
                $table->dateTime('reloadtime');
                $table->primary('nasipaddress');
            });
        }

        if (! $schema->hasTable('radpostauth')) {
            $schema->create('radpostauth', function (Blueprint $table) {
                $table->increments('id');
                $table->string('username', 64)->default('');
                $table->string('pass', 64)->default('');
                $table->string('reply', 32)->default('');
                $table->timestamp('authdate')->useCurrent();
                $table->string('class', 64)->nullable();
                $table->index('username');
            });
        }
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (! $this->puedeConectar()) {
            return;
        }

        $schema = Schema::connection($this->connectionName());
        $schema->dropIfExists('radpostauth');
        $schema->dropIfExists('nasreload');
        $schema->dropIfExists('radacct');
        $schema->dropIfExists('radgroupreply');
        $schema->dropIfExists('radgroupcheck');
        $schema->dropIfExists('radusergroup');
        $schema->dropIfExists('radreply');
        $schema->dropIfExists('radcheck');
        $schema->dropIfExists('nas');
    }
};
