<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuy com que o jogador voltou de uma eliminação provisória. A eliminação continua valendo
 * como histórico (caiu e voltou); desfazer o rebuy limpa a coluna e o jogador volta a estar fora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eliminacoes', function (Blueprint $table) {
            $table->foreignId('voltou_com')->nullable()->after('definitiva')->constrained('entradas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('eliminacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voltou_com');
        });
    }
};
