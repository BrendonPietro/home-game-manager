<?php

/*
|--------------------------------------------------------------------------
| Contas do clube
|--------------------------------------------------------------------------
|
| Quem administra o sistema de verdade (não os jogadores), criado pelo `php artisan db:seed` em
| qualquer ambiente, nos dois logins (site e painel), com o e-mail como login nos dois. Ficam no
| .env (fora do repositório): "Nome <email>", separados por vírgula. A senha é a inicial; cada um
| troca depois de entrar (site: Minha conta; painel: Perfil). Rodar o seed de novo não mexe em
| quem já existe. Detalhes em docs/deploy.md.
|
*/

return [

    // Faz tudo: grupo Desenvolvedor no painel e no site.
    'desenvolvedor' => env('CONTA_DESENVOLVEDOR'),
    'desenvolvedor_senha' => env('CONTA_DESENVOLVEDOR_SENHA'),

    // Grupo Admin no painel e no site.
    'admins' => env('CONTAS_ADMIN'),
    'admins_senha' => env('CONTAS_ADMIN_SENHA'),

];
