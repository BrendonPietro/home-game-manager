<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jogo_id')->constrained('jogos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('jogo_mesa_id')->nullable()->constrained('jogo_mesas')->nullOnDelete();
            $table->unsignedTinyInteger('lugar')->nullable();
            $table->string('status', 20)->default('confirmado'); // App\Enums\StatusParticipante
            $table->unsignedSmallInteger('posicao_final')->nullable();
            $table->decimal('premio', 10, 2)->nullable();
            $table->unsignedBigInteger('fichas_finais')->nullable(); // cash game
            $table->decimal('cash_out', 10, 2)->nullable();          // cash game
            $table->timestamps();

            $table->unique(['jogo_id', 'usuario_id']);
            $table->unique(['jogo_mesa_id', 'lugar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participantes');
    }
};
