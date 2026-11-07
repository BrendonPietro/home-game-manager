<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Em qualquer ambiente: permissões, regras, estruturas e as contas do clube (config/clube.php, do .env).
        $this->call([
            PermissoesSeeder::class,
            RegrasPremiacaoSeeder::class,
            EstruturasBlindsSeeder::class,
            ContasDoClubeSeeder::class,
        ]);

        // Só no ambiente local: as contas dev (painel "dev" e site dev@rodrigues.test), a noite de referência
        // (R$ 2.100 / 210 / 1.890) e as contas de teste (Operador e Geral). Em produção entram só as do clube.
        // Testes que precisam da noite rodam o PokerNight18Seeder direto.
        if (app()->environment('local')) {
            $this->call([SegurancaSeeder::class, AcessoSiteSeeder::class, PokerNight18Seeder::class, ContasDeTesteSeeder::class]);
        }
    }
}
