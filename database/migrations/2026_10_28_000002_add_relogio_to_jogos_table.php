<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2: relógio do torneio com estrutura de blinds (Services\Jogo\Relogio). Sem cron: o tempo que
 * falta sai de `relogio_segundos` menos o que passou desde `relogio_desde`; nulo = pausado.
 * `relogio_iniciado_em` é a primeira vez que o relógio correu (antes disso o botão é INICIAR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->foreignId('relogio_nivel_id')->nullable()->after('blinds_atual')
                ->constrained('estrutura_niveis')->nullOnDelete();
            $table->unsignedInteger('relogio_segundos')->nullable()->after('relogio_nivel_id');
            $table->timestamp('relogio_desde')->nullable()->after('relogio_segundos');
            $table->timestamp('relogio_iniciado_em')->nullable()->after('relogio_desde');
        });
    }

    public function down(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('relogio_nivel_id');
            $table->dropColumn(['relogio_segundos', 'relogio_desde', 'relogio_iniciado_em']);
        });
    }
};
