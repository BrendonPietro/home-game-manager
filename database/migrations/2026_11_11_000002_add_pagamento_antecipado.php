<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bônus do pagamento antecipado: quem pagou o buy-in antes ganha fichas a mais (Actions\Jogo\MarcarPagamentoAntecipado).
 * O jogo diz quantas; quem marca diz quem pagou antes (o participante); o buy-in guarda o que ganhou, como o time chip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->unsignedInteger('antecipado_fichas')->nullable()->after('time_chip_ate_nivel');
        });

        Schema::table('participantes', function (Blueprint $table) {
            $table->boolean('pagou_antes')->default(false)->after('status');
        });

        Schema::table('entradas', function (Blueprint $table) {
            $table->unsignedInteger('antecipado')->default(0)->after('time_chip');
        });
    }

    public function down(): void
    {
        Schema::table('entradas', function (Blueprint $table) {
            $table->dropColumn('antecipado');
        });

        Schema::table('participantes', function (Blueprint $table) {
            $table->dropColumn('pagou_antes');
        });

        Schema::table('jogos', function (Blueprint $table) {
            $table->dropColumn('antecipado_fichas');
        });
    }
};
