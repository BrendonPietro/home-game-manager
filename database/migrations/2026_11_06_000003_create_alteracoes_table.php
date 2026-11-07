<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico do jogo: quem mudou o quê e quando (fases, voltar fase, desfazer, acertos, finalizar).
 * Quem fez pelo site fica em `usuario_id`; pelo painel, em `seguranca_id`. Nada se apaga.
 *
 * `pagamentos.acerto_id`: os dois pagamentos que um acerto feito gera, para desfazer o acerto inteiro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alteracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jogo_id')->constrained('jogos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('seguranca_id')->nullable()->constrained('seguranca')->nullOnDelete();
            $table->string('tipo', 40);                 // App\Enums\TipoAlteracao
            $table->string('descricao', 255);
            $table->string('motivo', 255)->nullable();
            $table->json('dados')->nullable();          // antes e depois, para conferir
            $table->timestamp('created_at')->nullable();

            $table->index(['jogo_id', 'created_at']);
        });

        Schema::table('pagamentos', function (Blueprint $table) {
            $table->foreignId('acerto_id')->nullable()->after('participante_id')->constrained('acertos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acerto_id');
        });

        Schema::dropIfExists('alteracoes');
    }
};
