<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jogo_mesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jogo_id')->constrained('jogos')->cascadeOnDelete();
            $table->foreignId('mesa_id')->constrained('mesas')->restrictOnDelete();
            $table->foreignId('dealer_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->unsignedTinyInteger('lugares')->default(9);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['jogo_id', 'mesa_id']);
        });

        Schema::create('jogo_ranking', function (Blueprint $table) {
            $table->foreignId('jogo_id')->constrained('jogos')->cascadeOnDelete();
            $table->foreignId('ranking_id')->constrained('rankings')->cascadeOnDelete();

            $table->primary(['jogo_id', 'ranking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jogo_ranking');
        Schema::dropIfExists('jogo_mesas');
    }
};
