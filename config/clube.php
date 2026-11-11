<?php

/*
|--------------------------------------------------------------------------
| Contas do clube
|--------------------------------------------------------------------------
|
| Quem administra o sistema de verdade (não os jogadores), criado pelo `php artisan db:seed` em
| qualquer ambiente, nos dois logins (site e painel), com o mesmo usuário nos dois. Ficam no .env
| (fora do repositório): "Nome <usuário>", separados por vírgula; o usuário pode ser um nome
| ("FulanoDeTal") ou um e-mail. A senha é a inicial: a do painel cada um troca em Perfil, a do site
| muda no painel, em Usuários do site. Rodar o seed de novo não mexe em quem já existe.
| Detalhes em docs/deploy.md.
|
*/

return [

    // Faz tudo: grupo Desenvolvedor no painel e no site.
    'desenvolvedor' => env('CONTA_DESENVOLVEDOR'),
    'desenvolvedor_senha' => env('CONTA_DESENVOLVEDOR_SENHA'),

    // Grupo Admin no painel e no site.
    'admins' => env('CONTAS_ADMIN'),
    'admins_senha' => env('CONTAS_ADMIN_SENHA'),

    // Só no ambiente local e só com true: o db:seed cria também as contas dev, as de teste e a noite de
    // exemplo (Poker Night #18). Desligado, o banco fica limpo, pronto para usar de verdade.
    'dados_de_exemplo' => (bool) env('DADOS_DE_EXEMPLO', false),

];
