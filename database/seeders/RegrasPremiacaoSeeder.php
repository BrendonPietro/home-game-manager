<?php

namespace Database\Seeders;

use App\Models\RegraPremiacao;
use Illuminate\Database\Seeder;

/** Até 10: 65/35 · 11–18: 50/30/20 · 19+: 45/27/17/11. */
class RegrasPremiacaoSeeder extends Seeder
{
    public function run(): void
    {
        $regras = [
            [1, 10, [65, 35]],
            [11, 18, [50, 30, 20]],
            [19, null, [45, 27, 17, 11]],
        ];

        foreach ($regras as [$min, $max, $percentuais]) {
            RegraPremiacao::query()->updateOrCreate(
                ['jogadores_min' => $min],
                ['jogadores_max' => $max, 'percentuais' => $percentuais],
            );
        }
    }
}
