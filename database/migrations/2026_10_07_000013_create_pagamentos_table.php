<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamentos parciais ou totais. `direcao`: jogador_pagou (ao caixa) ou jogador_recebeu (do caixa).
 * Continua aceitando lançamentos depois de finalizar (pendências).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('jogo_id')->constrained('jogos')->cascadeOnDelete();
            $table->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $table->string('direcao', 20);              // App\Enums\DirecaoPagamento
            $table->decimal('valor', 10, 2);
            $table->string('forma', 20);                // App\Enums\FormaPagamento
            $table->string('observacao')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('cancelado_em')->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('motivo_cancelamento')->nullable();
            $table->timestamps();

            $table->index(['jogo_id', 'cancelado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
