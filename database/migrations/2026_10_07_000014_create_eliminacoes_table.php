<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Um jogador pode cair, voltar com rebuy e cair de novo. Antes do intervalo a eliminação
 * é provisória; depois, definitiva (e dá a posição em participantes.posicao_final).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eliminacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jogo_id')->constrained('jogos')->cascadeOnDelete();
            $table->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $table->unsignedSmallInteger('nivel')->nullable();
            $table->boolean('definitiva')->default(false);
            $table->foreignId('registrada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('cancelada_em')->nullable();
            $table->foreignId('cancelada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('motivo_cancelamento')->nullable();
            $table->timestamps();

            $table->index(['jogo_id', 'cancelada_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eliminacoes');
    }
};
