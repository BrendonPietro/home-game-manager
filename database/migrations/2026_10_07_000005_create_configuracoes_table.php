<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->id();
            $table->string('chave', 100)->unique();
            $table->json('valor')->nullable();
            $table->timestamps();
        });

        Schema::create('regras_premiacao', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('jogadores_min');
            $table->unsignedSmallInteger('jogadores_max')->nullable(); // nulo = sem limite
            $table->json('percentuais'); // ex.: [50, 30, 20]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regras_premiacao');
        Schema::dropIfExists('configuracoes');
    }
};
