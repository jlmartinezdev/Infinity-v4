<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_oauth_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 80)->unique();
            $table->string('client_secret_hash')->nullable();
            $table->string('client_name', 120);
            $table->json('redirect_uris');
            $table->string('token_endpoint_auth_method', 40)->default('none');
            $table->timestamps();
        });

        Schema::create('mcp_oauth_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code_hash', 64)->unique();
            $table->string('client_id', 80);
            $table->unsignedBigInteger('usuario_id');
            $table->string('redirect_uri', 500);
            $table->string('code_challenge', 128);
            $table->string('scope', 40)->default('whatsapp');
            $table->string('resource', 300)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
        });

        Schema::create('mcp_oauth_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('refresh_hash', 64)->nullable()->unique();
            $table->string('client_id', 80);
            $table->unsignedBigInteger('usuario_id');
            $table->string('scope', 40)->default('whatsapp');
            $table->timestamp('expires_at');
            $table->timestamp('refresh_expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_oauth_tokens');
        Schema::dropIfExists('mcp_oauth_codes');
        Schema::dropIfExists('mcp_oauth_clients');
    }
};
