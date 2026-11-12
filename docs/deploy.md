# Ambiente e deploy

## Agora: só local

- Pasta: `C:\laragon\www\rodrigues-home-game`.
- Banco MySQL próprio no Laragon (ex.: `rodrigues_home_game`).
- Host local do Laragon: `http://rodrigues-home-game.test`.
- `.env` com `APP_URL=http://rodrigues-home-game.test` (sempre com o esquema `http://`).
- O Laragon roda o PHP dentro do Apache (mod_php, thread-safe): o ambiente do processo é um só para todos os sites. Outro projeto Laravel atendendo ao mesmo tempo gravava o `.env` dele ali (`putenv`), e um pedido daqui lia o banco e a chave do outro (erro de banco, sessão perdida, tela de login). Por isso o `bootstrap/app.php` desliga o `putenv` no Apache (`Env::disablePutenv()`): o `.env` vai só para `$_SERVER` e `$_ENV`, que são de cada pedido. Na linha de comando (artisan, testes) nada muda.

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
CONTA_DESENVOLVEDOR="Nome <usuário>"   # contas do clube (ver Contas do clube, abaixo)
CONTA_DESENVOLVEDOR_SENHA=
CONTAS_ADMIN="Nome <usuário>"
CONTAS_ADMIN_SENHA=
DADOS_DE_EXEMPLO=false  # true = o seed local cria também as contas dev/teste e a Poker Night #18
PAINEL_SENHA_INICIAL=   # só com os dados de exemplo: senha da conta "dev" do painel; vazio = gera e mostra no terminal
SITE_SENHA_INICIAL=     # idem, da conta dev@rodrigues.test do site
```

Em `config/app.php`, `'timezone' => 'America/Sao_Paulo'`. Depois:

```bat
php artisan migrate:fresh --seed
php artisan test
```

O seed cria as permissões, os grupos (painel: Admin, Desenvolvedor, Geral; site: Desenvolvedor, Admin, Operador), as regras de premiação, as estruturas de blinds (Turbo 15 min e Deepstack 20 min) e as contas do clube. Isso é o **banco limpo**: o mesmo que vai para produção, sem jogo, jogador nem conta de teste. Com `DADOS_DE_EXEMPLO=true` (e `APP_ENV=local`), o seed cria também a conta `dev` do painel, a `dev@rodrigues.test` do site (Admin), as de teste (`operador@rodrigues.test`, `geral`) e a noite de exemplo Poker Night #18 (12 jogadores, 2 mesas, 22 entradas). O Filament (`composer require filament/filament:^5.0`) entra na fase 0, junto com o tema.

**Zerar o banco local** (ex.: antes de começar a usar de verdade): `migrate:fresh` apaga tudo, então faça antes o backup.

```bat
C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe -uroot --single-transaction rodrigues_home_game > storage\app\backups\antes-de-limpar.sql
php artisan migrate:fresh --seed
```

`storage/app/backups/` fica fora do git. Para voltar o backup: `mysql.exe -uroot rodrigues_home_game < storage\app\backups\antes-de-limpar.sql`.

## Produção: leticiaebrendon.com (Hostinger)

No ar desde 12/11/2026, no lugar do site do casamento, no plano **Business** da Hostinger (hPanel → Sites → leticiaebrendon.com). O projeto não depende de WebSocket, fila nem cron.

- **PHP 8.5** (hPanel → Avançado → Configuração do PHP), o mesmo do local. As extensões que o `composer.lock` pede já vêm ligadas. A Hostinger desliga `proc_open`, `exec`, `symlink` e afins (`disable_functions`): por isso o composer roda sem os scripts e o link do storage é feito no shell (abaixo).
- **SSH**: porta, usuário e IP em hPanel → Avançado → Acesso SSH (abaixo, `PORTA`, `USUARIO` e `IP`), com a chave `~/.ssh/hostinger_rhg` do PC de desenvolvimento (cadastrada no hPanel → Avançado → Acesso SSH, nome "rodrigues-home-game deploy"; para tirar o acesso, apague a chave lá).
- **Pastas**: o Laravel fica em `~/domains/leticiaebrendon.com/laravel`, e o `public_html` é um link para `laravel/public` (um `build/` só, sem sincronizar duas pastas). O `public/storage` é um link para `../storage/app/public`, feito com `ln -s`.
- **`.env` de produção** (só no servidor, `chmod 600`): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://leticiaebrendon.com`, `LOG_LEVEL=error`, `SESSION_SECURE_COOKIE=true`, `QUEUE_CONNECTION=sync` (sem worker), o banco e o usuário MySQL do site antigo, reaproveitados e o SMTP da Hostinger. As `CONTA_*` ficam vazias: as contas já estão no banco.
- **https**: o `public/.htaccess` manda http para https só quando o host é leticiaebrendon.com (o local continua em http).
- **Backups**: os diários da Hostinger (hPanel → Backups) e, antes de cada atualização, um dump à mão (abaixo). Em `~/backups` no servidor ficam o `.env` do site antigo e o dump do banco feito na subida.

### Atualizar o sistema

No PC (Git Bash), da pasta do projeto, com tudo commitado:

```bash
npm run build
git archive --format=tar -o /tmp/rhg.tar HEAD && tar -rf /tmp/rhg.tar public/build && gzip -f /tmp/rhg.tar
scp -i ~/.ssh/hostinger_rhg -P PORTA /tmp/rhg.tar.gz USUARIO@IP:rhg.tar.gz
ssh -i ~/.ssh/hostinger_rhg -p PORTA USUARIO@IP
```

No servidor:

```bash
cd ~/domains/leticiaebrendon.com/laravel
mysqldump --no-tablespaces --single-transaction -u USUARIO_DO_BANCO -p NOME_DO_BANCO > ~/backups/antes-$(date +%F).sql   # os dois estão no .env
php artisan down
tar -xzf ~/rhg.tar.gz && rm ~/rhg.tar.gz       # por cima: .env, storage e vendor ficam
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
php artisan package:discover
php artisan migrate --force
php artisan optimize && php artisan filament:optimize
php artisan up
```

- Permissão nova do site em uma versão (ex.: `site.fora_da_noite`): `php artisan db:seed --class=PermissoesSeeder --force` (volta os grupos padrão às permissões do seed).
- Arquivo que saiu do projeto continua no servidor (o `tar` só sobrescreve): apague à mão se importar.
- Primeira subida (já feita): o mesmo, mais `php artisan key:generate --force` e `ln -s ../storage/app/public public/storage`.

## Contas do clube

O `php artisan db:seed --force` da primeira subida cria as contas de quem administra (não os jogadores), no site e no painel, com o mesmo usuário nos dois (`config/clube.php`). O usuário pode ser um nome (só letras, números, ponto, traço e sublinhado) ou um e-mail; o login não diferencia maiúsculas:

```env
CONTA_DESENVOLVEDOR="Fulano de Tal <FulanoDeTal>"     # grupo Desenvolvedor: faz tudo
CONTA_DESENVOLVEDOR_SENHA=
CONTAS_ADMIN="Ciclano <Ciclano>, Beltrano <beltrano@exemplo.com>"   # grupo Admin
CONTAS_ADMIN_SENHA=
```

- Cada um entra com a senha inicial e troca a do painel em **Perfil** (menu da conta); a do site muda no painel, em Sistema → Usuários do site. Depois disso, as duas `*_SENHA` podem sair do `.env` de produção.
- Rodar o seed de novo não mexe em quem já existe (a senha trocada continua); conta nova entra acrescentando na lista e rodando `php artisan db:seed --class=ContasDoClubeSeeder --force`, ou pelo painel (Usuários do site e Contas do painel).
- As contas `dev` (painel) e `dev@rodrigues.test` (site) e as de teste só são criadas no ambiente local com `DADOS_DE_EXEMPLO=true`.
- Se o banco local limpo for o que sobe para produção, as contas já estão nele: o `db:seed --force` no servidor só acrescenta o que faltar.

## Backup

O banco é o caixa do clube (entradas, pagamentos, acertos e o histórico de quem mexeu em quê). Antes do piloto:

- Ligar o backup diário do MySQL da hospedagem (a maioria tem no painel) e conferir que dá para baixar um arquivo de lá.
- Antes de cada atualização do sistema, um dump na mão:

```bash
mysqldump --single-transaction --no-tablespaces -u USUARIO -p NOME_DO_BANCO > backup-$(date +%F).sql
```

- Uma vez por mês, restaurar um dump num banco local e abrir o painel nele, para saber que o backup volta de verdade.
- `storage/app/public` (fotos dos jogadores e o logo) entra no backup de arquivos da hospedagem.
- Nunca versionar `.env` com senhas e chaves reais.
