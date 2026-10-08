<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rankings: vários ativos ao mesmo tempo. Cada um define quais posições pontuam
 * (ranking_pontuacoes), se a mesa final pontua e se a participação pontua.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rankings', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('periodicidade', 20); // App\Enums\Periodicidade
            $table->date('inicio')->nullable();
            $table->date('fim')->nullable();
            $table->boolean('ft_pontua')->default(false);
            $table->unsignedInteger('pontos_mesa_final')->default(0);
            $table->boolean('participacao_pontua')->default(false);
            $table->unsignedInteger('pontos_participacao')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('ranking_pontuacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ranking_id')->constrained('rankings')->cascadeOnDelete();
            $table->unsignedSmallInteger('posicao');
            $table->unsignedInteger('pontos');
            $table->timestamps();

            $table->unique(['ranking_id', 'posicao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_pontuacoes');
        Schema::dropIfExists('rankings');
    }
};
