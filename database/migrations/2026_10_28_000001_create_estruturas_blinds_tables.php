<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2 (docs/regras.md, "Blinds e relógio"): estruturas de blinds reutilizáveis, com os níveis em ordem.
 * Intervalo é um nível especial (sem blinds, com nome). O torneio escolhe a estrutura; sem estrutura
 * o nível e os blinds continuam digitados na tela ao vivo, como na v1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estruturas_blinds', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('estrutura_niveis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estrutura_id')->constrained('estruturas_blinds')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->unsignedInteger('small_blind')->nullable();   // nulo no intervalo
            $table->unsignedInteger('big_blind')->nullable();
            $table->unsignedInteger('ante')->default(0);          // 0 = sem ante
            $table->unsignedSmallInteger('duracao_minutos');
            $table->boolean('e_intervalo')->default(false);
            $table->string('nome_intervalo', 40)->nullable();
            $table->timestamps();

            $table->index(['estrutura_id', 'ordem']);
        });

        Schema::table('jogos', function (Blueprint $table) {
            $table->foreignId('estrutura_blinds_id')->nullable()->after('tamanho_mesa_final')
                ->constrained('estruturas_blinds')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estrutura_blinds_id');
        });

        Schema::dropIfExists('estrutura_niveis');
        Schema::dropIfExists('estruturas_blinds');
    }
};
