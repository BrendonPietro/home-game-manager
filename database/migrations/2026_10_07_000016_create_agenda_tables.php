<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);                 // App\Enums\TipoEvento
            $table->string('nome');
            $table->date('data');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fim')->nullable();
            $table->foreignId('responsavel_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('jogo_id')->nullable()->unique()->constrained('jogos')->cascadeOnDelete();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('data');
        });

        Schema::create('evento_mesa', function (Blueprint $table) {
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('mesa_id')->constrained('mesas')->cascadeOnDelete();

            $table->primary(['evento_id', 'mesa_id']);
        });

        Schema::create('alugueis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->string('cliente');
            $table->decimal('valor', 10, 2);
            $table->string('status', 20)->default('reservado'); // App\Enums\StatusAluguel
            $table->timestamp('pago_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alugueis');
        Schema::dropIfExists('evento_mesa');
        Schema::dropIfExists('eventos');
    }
};
