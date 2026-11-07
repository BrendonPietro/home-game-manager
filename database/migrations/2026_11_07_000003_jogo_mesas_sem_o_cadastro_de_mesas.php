<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * As mesas do torneio deixam de apontar para o cadastro de mesas (que fica para aluguéis e reservas):
 * cada uma tem o próprio nome ("Mesa 1", "Mesa 2"…). As que já existem herdam o nome da mesa cadastrada.
 * O evento do jogo na agenda deixa de ocupar mesas (torneio e aluguel não se cruzam).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogo_mesas', function (Blueprint $table) {
            $table->string('nome', 40)->nullable()->after('jogo_id');
        });

        $nomes = DB::table('mesas')->pluck('nome', 'id');

        DB::table('jogo_mesas')->orderBy('id')->get(['id', 'mesa_id', 'ordem'])->each(
            fn (object $linha) => DB::table('jogo_mesas')->where('id', $linha->id)
                ->update(['nome' => $nomes[$linha->mesa_id] ?? 'Mesa '.$linha->ordem]),
        );

        Schema::table('jogo_mesas', function (Blueprint $table) {
            // O índice único (jogo_id, mesa_id) também servia à chave de jogo_id: ganha um índice próprio antes.
            $table->index('jogo_id');
            $table->dropForeign(['mesa_id']);
            $table->dropUnique(['jogo_id', 'mesa_id']);
            $table->dropColumn('mesa_id');
        });

        // O jogo não ocupa mais mesas na agenda.
        DB::table('evento_mesa')
            ->whereIn('evento_id', DB::table('eventos')->whereNotNull('jogo_id')->select('id'))
            ->delete();
    }

    public function down(): void
    {
        Schema::table('jogo_mesas', function (Blueprint $table) {
            $table->foreignId('mesa_id')->nullable()->after('jogo_id')->constrained('mesas')->restrictOnDelete();
        });

        $porNome = DB::table('mesas')->pluck('id', 'nome');

        DB::table('jogo_mesas')->orderBy('id')->get(['id', 'nome'])->each(
            fn (object $linha) => DB::table('jogo_mesas')->where('id', $linha->id)->update(['mesa_id' => $porNome[$linha->nome] ?? null]),
        );

        Schema::table('jogo_mesas', function (Blueprint $table) {
            $table->dropColumn('nome');
        });
    }
};
