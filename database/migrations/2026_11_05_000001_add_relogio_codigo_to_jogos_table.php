<?php

use App\Models\Jogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v3: link do relógio do torneio (`/relogio/{codigo}`), aberto sem login e só para ver. Cada jogo
 * nasce com um código (Jogo::booted); trocar o link gera outro e o antigo para de abrir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->string('relogio_codigo', 16)->nullable()->unique()->after('relogio_iniciado_em');
        });

        foreach (DB::table('jogos')->whereNull('relogio_codigo')->pluck('id') as $id) {
            DB::table('jogos')->where('id', $id)->update(['relogio_codigo' => Jogo::novoCodigoDoRelogio()]);
        }
    }

    public function down(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->dropUnique(['relogio_codigo']);
            $table->dropColumn('relogio_codigo');
        });
    }
};
