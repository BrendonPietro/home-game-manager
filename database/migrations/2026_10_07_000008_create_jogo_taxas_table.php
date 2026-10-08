<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taxas tiradas da premiação antes de dividir (dealers, casa…).
 * `calculo`: fixo, percentual (sobre a premiação bruta), por_unidade (valor × quantidade),
 * por_jogador, por_entrada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jogo_taxas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jogo_id')->constrained('jogos')->cascadeOnDelete();
            $table->string('nome', 60);
            $table->string('calculo', 20);              // App\Enums\CalculoTaxa
            $table->decimal('valor', 10, 2);            // R$ ou % conforme o cálculo
            $table->unsignedSmallInteger('quantidade')->nullable(); // por_unidade
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jogo_taxas');
    }
};
