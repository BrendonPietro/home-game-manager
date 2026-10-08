<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jogo_premiacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jogo_id')->constrained('jogos')->cascadeOnDelete();
            $table->unsignedSmallInteger('posicao');
            $table->decimal('percentual', 5, 2);
            $table->decimal('valor', 10, 2)->nullable(); // gravado ao fechar entradas
            $table->timestamps();

            $table->unique(['jogo_id', 'posicao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jogo_premiacoes');
    }
};
