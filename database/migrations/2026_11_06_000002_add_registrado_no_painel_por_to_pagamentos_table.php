<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamento antecipado lançado pelo painel (pré-torneio): a conta do painel que lançou. Pelo site,
 * continua `registrado_por` (usuarios).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->foreignId('registrado_no_painel_por')->nullable()->after('registrado_por')
                ->constrained('seguranca')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registrado_no_painel_por');
        });
    }
};
