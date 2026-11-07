<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De qual torneio modelo o jogo saiu (Torneio de sábado, Deepstack…): o tipo de evento das Métricas.
 * Criar a partir do modelo e Duplicar preenchem; no cadastro do jogo dá para escolher à mão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->foreignId('modelo_id')->nullable()->after('nome_modelo')->constrained('jogos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modelo_id');
        });
    }
};
