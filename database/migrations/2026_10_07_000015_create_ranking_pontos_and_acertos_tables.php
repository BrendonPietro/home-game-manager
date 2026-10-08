<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gravado ao finalizar; mudar a regra do ranking depois não altera pontos já dados.
        Schema::create('ranking_pontos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ranking_id')->constrained('rankings')->cascadeOnDelete();
            $table->foreignId('jogo_id')->constrained('jogos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->unsignedSmallInteger('posicao')->nullable();
            $table->unsignedInteger('pontos')->default(0);
            $table->json('detalhe')->nullable(); // {posicao: x, mesa_final: y, participacao: z}
            $table->timestamps();

            $table->unique(['ranking_id', 'jogo_id', 'usuario_id']);
        });

        // Sugestão de "quem paga quem" no fechamento. Participante nulo = o caixa.
        Schema::create('acertos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jogo_id')->constrained('jogos')->cascadeOnDelete();
            $table->foreignId('de_participante_id')->nullable()->constrained('participantes')->cascadeOnDelete();
            $table->foreignId('para_participante_id')->nullable()->constrained('participantes')->cascadeOnDelete();
            $table->decimal('valor', 10, 2);
            $table->boolean('pago')->default(false);
            $table->timestamp('pago_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acertos');
        Schema::dropIfExists('ranking_pontos');
    }
};
