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
            SegurancaSeeder::class,
            AcessoSiteSeeder::class,
            // PokerNight18Seeder::class, // fase 4: noite de referência (R$ 2.100 / 210 / 1.890)
        ]);
    }
}
