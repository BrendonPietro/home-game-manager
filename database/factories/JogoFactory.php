<?php

namespace Database\Factories;

use App\Enums\FaseTorneio;
use App\Enums\Modalidade;
use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Models\Jogo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Jogo> */
class JogoFactory extends Factory
{
    protected $model = Jogo::class;

    public function definition(): array
    {
        return [
            'modalidade' => Modalidade::Torneio,
            'nome' => 'Poker Night #'.fake()->unique()->numberBetween(1, 999),
            'data' => fake()->dateTimeBetween('now', '+1 month'),
            'horario' => '20:00',
        ];
    }

    public function agendado(): static
    {
        return $this->state(fn () => ['status' => StatusJogo::Agendado]);
    }

    public function emAndamento(): static
    {
        return $this->state(fn (array $atributos) => [
            'status' => StatusJogo::EmAndamento,
            'fase' => ($atributos['modalidade'] ?? null) === Modalidade::CashGame ? null : FaseTorneio::Rebuys,
            'iniciado_em' => now(),
        ]);
    }

    public function finalizado(): static
    {
        return $this->state(fn () => ['status' => StatusJogo::Finalizado, 'finalizado_em' => now()]);
    }

    public function cashGame(): static
    {
        return $this->state(fn () => [
            'modalidade' => Modalidade::CashGame,
            'valor_ficha' => '0.0100',
        ]);
    }

    public function modelo(string $nome = 'Torneio de sábado'): static
    {
        return $this->state(fn () => [
            'e_modelo' => true,
            'nome_modelo' => $nome,
            'data' => null,
        ]);
    }

    /** Buy-in, rebuy e add-on padrão (o que o cadastro já traz). */
    public function comEntradasPadrao(string $valor = '100.00', int $fichas = 20000): static
    {
        return $this->afterCreating(function (Jogo $jogo) use ($valor, $fichas) {
            foreach (TipoItem::entradas() as $ordem => $tipo) {
                $jogo->itens()->create([
                    'tipo' => $tipo,
                    'nome' => $tipo->label(),
                    'valor' => $valor,
                    'fichas' => $fichas,
                    'limite_por_jogador' => $tipo === TipoItem::Rebuy ? null : 1,
                    'padrao' => true,
                    'ordem' => $ordem,
                ]);
            }
        });
    }
}
