<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Torneio ou cash game (modalidade). `e_modelo = true` é torneio modelo:
 * só serve para copiar, não aparece no site nem no calendário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jogos', function (Blueprint $table) {
            $table->id();
            $table->boolean('e_modelo')->default(false);
            $table->string('nome_modelo')->nullable();
            $table->string('modalidade', 20)->default('torneio');          // App\Enums\Modalidade
            $table->foreignId('temporada_id')->nullable()->constrained('temporadas')->nullOnDelete();
            $table->string('nome');
            $table->date('data')->nullable();                                // nulo em modelos
            $table->time('horario')->nullable();
            $table->string('status', 20)->default('rascunho');               // App\Enums\StatusJogo
            $table->string('fase', 20)->nullable();                          // App\Enums\FaseTorneio
            $table->foreignId('floor_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('metodo_premiacao', 20)->default('regra');        // App\Enums\MetodoPremiacao
            $table->unsignedTinyInteger('lugares_por_mesa')->default(9);
            $table->unsignedTinyInteger('tamanho_mesa_final')->default(9);
            $table->unsignedSmallInteger('nivel_atual')->nullable();         // v1: digitado
            $table->string('blinds_atual', 40)->nullable();                  // v1: digitado
            $table->decimal('valor_ficha', 12, 4)->nullable();               // cash game
            $table->text('observacoes')->nullable();
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('rebuys_encerrados_em')->nullable();
            $table->timestamp('entradas_fechadas_em')->nullable();
            $table->timestamp('finalizado_em')->nullable();
            $table->timestamps();

            $table->index(['e_modelo', 'status', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jogos');
    }
};
