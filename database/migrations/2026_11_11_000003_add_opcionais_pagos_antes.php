<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opcionais pagos antes (taxa da pizza…): o opcional diz que pode ser pago antes (`jogo_itens.pago_antes`) e
 * cada jogador marcado (`participante_opcionais`) leva o opcional junto do buy-in, com as fichas, ao sentar
 * (Actions\Jogo\MarcarOpcionalPagoAntes e RegistrarEntrada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogo_itens', function (Blueprint $table) {
            $table->boolean('pago_antes')->default(false)->after('obrigatorio');
        });

        Schema::create('participante_opcionais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $table->foreignId('jogo_item_id')->constrained('jogo_itens')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['participante_id', 'jogo_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participante_opcionais');

        Schema::table('jogo_itens', function (Blueprint $table) {
            $table->dropColumn('pago_antes');
        });
    }
};
