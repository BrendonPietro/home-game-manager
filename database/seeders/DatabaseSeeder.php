<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissoesSeeder::class,
            RegrasPremiacaoSeeder::class,
            EstruturasBlindsSeeder::class,
            SegurancaSeeder::class,
            AcessoSiteSeeder::class,
        ]);

        // Noite de referência (R$ 2.100 / 210 / 1.890) e contas de teste (Operador e Geral): só no
        // ambiente local. Testes que precisam da noite rodam o PokerNight18Seeder direto.
        if (app()->environment('local')) {
            $this->call([PokerNight18Seeder::class, ContasDeTesteSeeder::class]);
        }
    }
}
