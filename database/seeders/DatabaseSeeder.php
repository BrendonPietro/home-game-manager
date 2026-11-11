<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sempre: permissões, regras, estruturas e as contas do clube (config/clube.php, do .env).
        // Todos podem rodar de novo sem duplicar (ex.: depois de uma atualização que traz permissão nova).
        $this->call([
            PermissoesSeeder::class,
            RegrasPremiacaoSeeder::class,
            EstruturasBlindsSeeder::class,
            ContasDoClubeSeeder::class,
        ]);

        // Só no ambiente local com DADOS_DE_EXEMPLO=true: as contas dev (painel "dev" e site dev@rodrigues.test),
        // a noite de referência (R$ 2.100 / 210 / 1.890) e as contas de teste (Operador e Geral). Sem isso o banco
        // fica limpo, só com as contas do clube. Testes que precisam da noite rodam o PokerNight18Seeder direto.
        if (app()->environment('local') && config('clube.dados_de_exemplo')) {
            $this->call([SegurancaSeeder::class, AcessoSiteSeeder::class, PokerNight18Seeder::class, ContasDeTesteSeeder::class]);
        }
    }
}
