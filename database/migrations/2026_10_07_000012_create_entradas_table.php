<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada item comprado (o que o jogador deve). Valor, fichas e destino são copiados
 * do jogo_item no momento do lançamento. Nunca apagada: desfazer preenche cancelada_em.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entradas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // vem do clique: toque duplo não grava duas vezes
            $table->foreignId('jogo_id')->constrained('jogos')->cascadeOnDelete();
            $table->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $table->foreignId('jogo_item_id')->constrained('jogo_itens')->restrictOnDelete();
            $table->string('tipo', 20);                 // App\Enums\TipoItem
            $table->decimal('valor', 10, 2);
            $table->unsignedInteger('fichas')->default(0);
            $table->string('destino', 20);              // App\Enums\DestinoItem
            $table->foreignId('vinculada_a')->nullable()->constrained('entradas')->nullOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('cancelada_em')->nullable();
            $table->foreignId('cancelada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('motivo_cancelamento')->nullable();
            $table->timestamps();

            $table->index(['jogo_id', 'cancelada_em']);
            $table->index(['participante_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entradas');
    }
};
