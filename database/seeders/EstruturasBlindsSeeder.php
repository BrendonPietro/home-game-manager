<?php

namespace Database\Seeders;

use App\Models\EstruturaBlinds;
use Illuminate\Database\Seeder;

/**
 * Duas estruturas para começar (docs/regras.md, "Blinds e relógio"). O nível 6 do Turbo é o
 * 100 / 200 da Poker Night #18. Só cria a que falta: rodar de novo não mexe nas que o clube editou.
 */
class EstruturasBlindsSeeder extends Seeder
{
    public const TURBO = 'Turbo 15 min';

    public const DEEPSTACK = 'Deepstack 20 min';

    public function run(): void
    {
        $this->estrutura(self::TURBO, 'Níveis de 15 min. Intervalo depois do nível 6: fim dos rebuys e add-on.', 15, 15, [
            [10, 20], [20, 40], [30, 60], [50, 100], [75, 150], [100, 200],
            'Intervalo',
            [150, 300], [200, 400], [300, 600], [400, 800], [500, 1000],
            [700, 1400], [1000, 2000], [1500, 3000], [2000, 4000], [3000, 6000],
        ]);

        $this->estrutura(self::DEEPSTACK, 'Níveis de 20 min. Intervalo depois do nível 6; ante do big blind a partir do nível 7.', 20, 20, [
            [25, 50], [50, 100], [75, 150], [100, 200], [150, 300], [200, 400],
            'Intervalo',
            [300, 600, 600], [400, 800, 800], [500, 1000, 1000], [600, 1200, 1200],
            [800, 1600, 1600], [1000, 2000, 2000], [1500, 3000, 3000], [2000, 4000, 4000],
        ]);
    }

    /** @param  list<array{0: int, 1: int, 2?: int}|string>  $niveis  [small, big, ante?] ou o nome do intervalo */
    private function estrutura(string $nome, string $descricao, int $minutos, int $intervalo, array $niveis): void
    {
        $estrutura = EstruturaBlinds::query()->firstOrCreate(['nome' => $nome], ['descricao' => $descricao]);

        if (! $estrutura->wasRecentlyCreated) {
            return;
        }

        foreach ($niveis as $ordem => $nivel) {
            $estrutura->niveis()->create(is_string($nivel)
                ? ['ordem' => $ordem + 1, 'e_intervalo' => true, 'nome_intervalo' => $nivel, 'duracao_minutos' => $intervalo]
                : ['ordem' => $ordem + 1, 'small_blind' => $nivel[0], 'big_blind' => $nivel[1], 'ante' => $nivel[2] ?? 0, 'duracao_minutos' => $minutos]);
        }
    }
}
