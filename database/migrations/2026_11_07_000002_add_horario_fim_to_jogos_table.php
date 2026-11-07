<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Término previsto do jogo: o site fecha para quem não é admin 3 h depois dele (App\Support\JanelaDoSite).
 * Antes do horário de início = no dia seguinte (o torneio passou da meia-noite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->time('horario_fim')->nullable()->after('horario');
        });
    }

    public function down(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->dropColumn('horario_fim');
        });
    }
};
