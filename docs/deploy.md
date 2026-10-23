# Ambiente e deploy

## Agora: só local

- Pasta: `C:\laragon\www\rodrigues-home-game`.
- Banco MySQL próprio no Laragon (ex.: `rodrigues_home_game`).
- Host local do Laragon: `http://rodrigues-home-game.test`.
- `.env` com `APP_URL=http://rodrigues-home-game.test` (sempre com o esquema `http://`).

### Criando o projeto

A pasta já tem os docs, as migrations, os models, os enums, os seeders e as configs de `auth` e `permission`. O `composer create-project` não instala numa pasta com arquivos, então o skeleton entra por uma pasta temporária, **sem sobrescrever** o que já existe:

```bat
cd C:\laragon\www
composer create-project laravel/laravel:^13.0 rodrigues-home-game-tmp
robocopy rodrigues-home-game-tmp rodrigues-home-game /E /XC /XN /XO
rmdir /S /Q rodrigues-home-game-tmp
cd rodrigues-home-game
del app\Models\User.php database\factories\UserFactory.php
composer config platform.php 8.5
composer update
composer require spatie/laravel-permission:^6.0
```

`/XC /XN /XO` fazem o robocopy pular arquivos que já existem. A migration `0001_01_01_000000_create_users_table.php` daqui tem o mesmo nome da padrão de propósito: ela cria `usuarios` e `seguranca` no lugar de `users`.

**Não** rode `vendor:publish` do spatie: `config/permission.php` e a migration das permissões já estão aqui, com as tabelas em português.

No `.env`:

```env
APP_NAME="Rodrigues Home Game"
APP_URL=http://rodrigues-home-game.test
APP_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR
DB_CONNECTION=mysql
DB_DATABASE=rodrigues_home_game
DB_USERNAME=root
DB_PASSWORD=
PAINEL_SENHA_INICIAL=   # opcional: senha da conta "dev" do painel; vazio = gera e mostra no terminal
SITE_SENHA_INICIAL=     # opcional: senha da conta dev@rodrigues.test do site; idem
```

Em `config/app.php`, `'timezone' => 'America/Sao_Paulo'`. Depois:

```bat
php artisan migrate:fresh --seed
php artisan test
```

O seed cria as permissões, os grupos (painel: Admin, Desenvolvedor, Geral; site: Admin, Operador), as regras de premiação, a conta `dev` do painel e a conta `dev@rodrigues.test` do site (grupo Admin). Em produção, desative ou troque as duas depois do primeiro acesso. O Filament (`composer require filament/filament:^5.0`) entra na fase 0, junto com o tema.

## Lançamento: domínio e hospedagem novos (a definir)

O projeto não depende de WebSocket, fila nem cron, então funciona em hospedagem compartilhada com PHP 8.5 e MySQL. Fluxo previsto, igual ao do casamento (FTP + SSH):

1. Local: `npm run build` (site + tema do Filament em `public/build/`).
2. Subir os arquivos alterados; `vendor/` quando mudar dependência (ou `composer install` no servidor).
3. Se a hospedagem separar docroot (`public_html`) e a pasta do Laravel, sincronizar as **duas** pastas `build/` — senão o `@vite` aponta para hashes que não existem e o JS dá 404.
4. No servidor:

```bash
php artisan migrate --force
php artisan filament:assets          # se o Filament mudou
php artisan storage:link             # primeira vez (fotos, logo)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Produção

- `APP_URL` com `https://`, `APP_ENV=production`, `APP_DEBUG=false`.
- PHP 8.5 (Laravel 13 recebe correções de segurança até 17/03/2028). Conferir a versão do PHP antes de contratar a hospedagem.
- Nunca versionar `.env` com senhas e chaves reais.
