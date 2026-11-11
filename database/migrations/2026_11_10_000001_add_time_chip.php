<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time chip: fichas a mais para quem entra no torneio (buy-in) até o fim de um nível (App\Services\Jogo\TimeChip).
 * O jogo diz quanto e até que nível; o buy-in guarda o que ganhou, e desfazer o buy-in leva junto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->unsignedInteger('time_chip_fichas')->nullable()->after('tamanho_mesa_final');
            $table->unsignedTinyInteger('time_chip_ate_nivel')->default(1)->after('time_chip_fichas');
        });

        Schema::table('entradas', function (Blueprint $table) {
            $table->unsignedInteger('time_chip')->default(0)->after('fichas');
        });
    }

    public function down(): void
    {
        Schema::table('entradas', function (Blueprint $table) {
            $table->dropColumn('time_chip');
        });

        Schema::table('jogos', function (Blueprint $table) {
            $table->dropColumn(['time_chip_fichas', 'time_chip_ate_nivel']);
        });
    }
};
