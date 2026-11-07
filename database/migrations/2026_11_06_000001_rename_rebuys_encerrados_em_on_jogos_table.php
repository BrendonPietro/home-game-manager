<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O botão do meio do torneio deixou de encerrar os rebuys: ABRIR INTERVALO abre o add-on e o rebuy
 * continua até FECHAR ENTRADAS. A coluna passa a dizer quando o intervalo foi aberto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->renameColumn('rebuys_encerrados_em', 'intervalo_aberto_em');
        });
    }

    public function down(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->renameColumn('intervalo_aberto_em', 'rebuys_encerrados_em');
        });
    }
};
