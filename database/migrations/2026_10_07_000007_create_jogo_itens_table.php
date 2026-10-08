<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O que o jogador pode comprar no jogo.
 *
 * - Entradas (`buyin`, `rebuy`, `addon`): uma linha `padrao` de cada já vem no cadastro
 *   (não se apaga, só desativa). O "+" cria variações do mesmo tipo (buy-in duplo, rebuy duplo).
 *   Sempre `destino = premiacao`.
 * - Opcionais (`opcional`): opcional de fichas, churrasco… com destino
 *   `premiacao` (vai), `metade` (50/50) ou `fora` (não vai).
 *   `compravel_com`: ["buyin","rebuy","addon"] (junto de cada entrada) ou ["jogador"] (1 por jogador).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jogo_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jogo_id')->constrained('jogos')->cascadeOnDelete();
            $table->string('tipo', 20);                          // App\Enums\TipoItem
            $table->string('nome', 60);
            $table->decimal('valor', 10, 2);
            $table->unsignedInteger('fichas')->default(0);
            $table->string('destino', 20)->default('premiacao'); // App\Enums\DestinoItem
            $table->json('compravel_com')->nullable();
            $table->boolean('obrigatorio')->default(false);
            $table->unsignedSmallInteger('limite_por_jogador')->nullable(); // nulo = sem limite
            $table->boolean('padrao')->default(false);
            $table->boolean('ativo')->default(true);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['jogo_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jogo_itens');
    }
};
