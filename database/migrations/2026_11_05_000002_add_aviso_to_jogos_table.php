<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3: AVISO do gerenciador do relógio (Actions\Jogo\DispararAviso). `avisos` conta quantos foram
 * disparados (as telas tocam quando o número sobe) e `aviso_em` é o último, para uma tela que volta
 * da falta de internet não tocar um aviso velho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->unsignedInteger('avisos')->default(0)->after('relogio_codigo');
            $table->timestamp('aviso_em')->nullable()->after('avisos');
        });
    }

    public function down(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->dropColumn(['avisos', 'aviso_em']);
        });
    }
};
