<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quanto de cada item vai para a premiação deixa de ser um destino fixo (vai / 50/50 / não vai) e vira
 * um percentual livre de 0 a 100, para entradas e opcionais (ex.: buy-in 100%, churrasco 0%, opcional 75%).
 * O que já existe passa assim: premiacao → 100, metade → 50, fora → 0. A entrada copia o do item na hora.
 */
return new class extends Migration
{
    private const TABELAS = ['jogo_itens', 'entradas'];

    public function up(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->decimal('percentual_premiacao', 5, 2)->default(100)->after('fichas');
            });

            DB::table($tabela)->update([
                'percentual_premiacao' => DB::raw("CASE destino WHEN 'metade' THEN 50 WHEN 'fora' THEN 0 ELSE 100 END"),
            ]);

            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('destino');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->string('destino', 20)->default('premiacao')->after('fichas');
            });

            // O que não é 100, 50 ou 0 volta como "vai" (o destino antigo não tinha outro percentual).
            DB::table($tabela)->update([
                'destino' => DB::raw("CASE WHEN percentual_premiacao = 50 THEN 'metade' WHEN percentual_premiacao = 0 THEN 'fora' ELSE 'premiacao' END"),
            ]);

            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('percentual_premiacao');
            });
        }
    }
};
