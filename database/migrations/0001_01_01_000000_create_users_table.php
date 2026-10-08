<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Substitui a migration padrão de `users` do Laravel (mesmo nome de arquivo,
 * para não ser sobrescrita ao copiar o skeleton do create-project).
 *
 * - `usuarios`: todas as pessoas (jogadores, dealers, floor, quem opera o site). Guard `web`.
 * - `seguranca`: contas do painel /painel. Guard `painel`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('apelido', 60)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('foto')->nullable();
            $table->boolean('acessa_site')->default(false);
            $table->string('email')->nullable()->unique();
            $table->string('password')->nullable();
            $table->boolean('pode_ser_dealer')->default(false);
            $table->boolean('pode_ser_floor')->default(false);
            $table->boolean('ativo')->default(true);
            $table->text('observacoes')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ativo', 'nome']);
        });

        Schema::create('seguranca', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('login', 60)->unique();
            $table->string('password');
            $table->boolean('ativo')->default(true);
            $table->timestamp('ultimo_acesso')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('seguranca');
        Schema::dropIfExists('usuarios');
    }
};
