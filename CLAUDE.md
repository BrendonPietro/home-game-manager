# CLAUDE.md

Orientações para o Claude Code trabalhar neste repositório.

## Projeto

Sistema de gerenciamento do **Rodrigues Home Game**, um home game semanal de poker **presencial** (não é poker online). Substitui a planilha da noite: torneios e cash games, jogadores, mesas, dealers e floor, buy-ins, rebuys, add-ons e variações, opcionais (com destino na premiação), taxas do torneio, pagamentos, prize pool, premiação, acertos, torneios modelo e rankings.

Um app Laravel só, com duas entradas:

- **Site de operação** em `/`: usado na mesa durante a noite (notebook, tablet e celular). Blade + Livewire 4 + Alpine.js com o design system do clube.
- **Painel administrativo** em `/painel`: Filament 5 tematizado, usado no PC para configurar torneios, calendário, aluguéis e relatórios.

Stack: **PHP 8.5** (`config.platform.php = 8.5` no composer), **Laravel 13**, Filament 5, Livewire 4, Vite, SCSS, MySQL. Por enquanto roda só local no Laragon; domínio e hospedagem do lançamento ainda não foram escolhidos.

> PHP 8.5 + Laravel 13 desde 22/10/2026 (antes PHP 8.2 + Laravel 12, que só teria segurança até 24/02/2027). O Laravel 13 recebe correções de bugs até o 3º trimestre de 2027 e de segurança até 17/03/2028. A hospedagem do lançamento precisa oferecer PHP 8.5. Não trocar a versão do PHP sem combinar.

## Comandos

```bash
# Desenvolvimento
npm run dev                              # Vite (HMR)
php artisan serve                        # ou o host do Laragon: http://rodrigues-home-game.test

# Build
npm run build                            # site (SCSS/JS) + tema do Filament para public/build/

# Banco
php artisan migrate --seed               # migrations + noite de exemplo (Poker Night #18)

# Testes
php artisan test
php artisan test --filter=CalculadoraTest

# Filament
php artisan make:filament-resource NomeDoModel
php artisan filament:assets              # republicar assets (o composer já roda filament:upgrade após update)

# Site
http://rodrigues-home-game.test/vitrine  # vitrine do design system (só APP_ENV=local)
```

## Arquitetura

Detalhes em [docs/arquitetura.md](docs/arquitetura.md). O essencial:

### Regras da noite ficam num lugar só

Site e painel **nunca gravam entradas ou status direto pelo model**. Tudo passa por:

| Classe | Faz |
|---|---|
| `App\Services\Jogo\Calculadora` | Arrecadação, premiação bruta, taxas, prize pool e prêmios, a partir de `entradas` |
| `App\Services\Jogo\Saldos` | Investido, pago, recebido e saldo por jogador (`entradas` + `pagamentos`) |
| `App\Services\Jogo\Acertos` | Sugestão de "quem paga quem" no fechamento |
| `App\Services\Ranking\Pontuacao` | Pontos por ranking (posição + bônus de mesa final + participação) |
| `App\Actions\Jogo\RegistrarEntrada` | Buy-in, rebuy, add-on e opcionais marcados, conforme a fase |
| `App\Actions\Jogo\RegistrarPagamento` | Pagamento parcial ou total (jogador pagou / recebeu) |
| `App\Actions\Jogo\RegistrarEliminacao` | Provisória antes do intervalo, definitiva depois (dá a posição) |
| `App\Actions\Jogo\RegistrarCashOut` | Cash game: fichas finais × valor da ficha |
| `App\Actions\Jogo\TrocarLugar` | Muda mesa/lugar (quebra de mesa) |
| `App\Actions\Jogo\DesfazerAcao` | Cancela a última ação do jogador, com motivo (nunca apaga) |
| `App\Actions\Jogo\EncerrarRebuys`, `FecharEntradas`, `FinalizarJogo` | Fases e status |
| `App\Actions\Jogo\CriarDeModelo` | Novo jogo a partir de um torneio modelo (`jogos.e_modelo`) |

Toda ação roda numa transação com `lockForUpdate()` na linha do jogo.

Regras e fórmulas em [docs/regras.md](docs/regras.md).

### `entradas` e `pagamentos` são o caixa

Cada buy-in, rebuy e add-on é uma linha em `entradas` (o que o jogador deve), com `valor` e `fichas` copiados da opção no momento do registro. Cada pagamento, parcial ou total, é uma linha em `pagamentos` (jogador pagou ao caixa ou recebeu do caixa). Nada é apagado; desfazer preenche `cancelada_em`/`cancelado_em` (trait `App\Models\Concerns\Cancelavel`: use sempre `->validas()` nas somas). Cada jogo define em `jogo_itens` as **entradas** (buy-in, rebuy e add-on padrão + variações como rebuy duplo) e os **opcionais** (opcional, churrasco…, com `destino` `premiacao` / `metade` / `fora`), e em `jogo_taxas` as **taxas** (dealers, casa…), tiradas da premiação antes de dividir. Prize pool = premiação bruta − taxas. Só o Admin do site altera isso durante o jogo, até fechar entradas. Saldo do jogador = investido − prêmio − pago + recebido. Os dois têm `uuid` único vindo do clique, para um toque duplo não registrar duas vezes.

### Jogo: torneio ou cash game

`jogos.modalidade` = `torneio` ou `cash_game`. Status: `rascunho` → `agendado` → `em_andamento` (Iniciar jogo) → `finalizado` (Finalizar). `cancelado` só a partir de `rascunho`/`agendado`.

No torneio, `em_andamento` tem fases (`jogos.fase`): `rebuys` (eliminado pode voltar com rebuy) → `intervalo` (Encerrar rebuys: abre add-on, eliminações viram definitivas) → `sem_entradas` (Fechar entradas: grava prêmios; eliminações dão a posição na hora). No cash game não há eliminação: o jogador faz cash-out (fichas finais × valor da ficha).

O middleware `jogo.etapa` redireciona para a etapa atual quando alguém abre uma tela que o status/fase ainda não permite.

### Tempo real

Hospedagem compartilhada não roda WebSocket. A tela ao vivo e o resumo usam `wire:poll.5s`. Não adicionar Reverb/Pusher sem mudar a hospedagem.

### Acesso: dois logins e permissões

- **Painel** → guard `painel`, tabela `seguranca` (model `App\Models\Seguranca`), `->authGuard('painel')` no provider do Filament.
- **Site** → guard `web`, tabela `usuarios` (model `App\Models\Usuario`). `usuarios` guarda todas as pessoas, inclusive jogadores sem login (`acessa_site = false`). Jogos, pontos e dinheiro se ligam ao `id` daqui. Checkboxes `pode_ser_dealer` e `pode_ser_floor` alimentam os selects de dealer e floor. Login do site (`AuthController`, `/login`) pelo **e-mail** (a tabela não tem coluna de login), só com `acessa_site` e `ativo`; 5 tentativas erradas por e-mail+IP bloqueiam por 1 minuto.
- Contas de desenvolvimento do seed: painel `dev` (`PAINEL_SENHA_INICIAL`) e site `dev@rodrigues.test`, grupo Admin (`SITE_SENHA_INICIAL`). Sem a variável, a senha é gerada e mostrada no terminal.
- **Permissões** com `spatie/laravel-permission` v6 nos dois guards (`guard_name` separa painel e site). Tabelas renomeadas em `config/permission.php`: `grupos`, `permissoes`, `grupo_permissoes`, `modelo_grupos`, `modelo_permissoes`. Grupos iniciais (seeder): painel Admin, Desenvolvedor, Geral; site Admin, Operador (Operador só lança entradas, pagamentos e eliminações). Formato `recurso.acao` (ex.: `jogos.editar`, `pagamentos.registrar`). Painel: policies por resource. Site: checar no Action e esconder botão com `@can`.
- Nunca checar permissão comparando nome de grupo; sempre pela permissão.

### Rankings e modelos

- Vários rankings ao mesmo tempo (`rankings`); o jogo (torneio ou cash game) escolhe em quais vale ponto (`jogo_ranking`). Cada ranking configura posições pontuadas, se FT pontua e se participação pontua. Pontos gravados em `ranking_pontos` ao finalizar.
- Torneio modelo = `jogos.e_modelo = true`; nunca aparece no site nem no calendário.

### Blinds e relógio

V2. Na v1, `jogos.nivel_atual` e `blinds_atual` são digitados. Detalhes em [docs/regras.md](docs/regras.md).

## Convenções

- Tabelas e colunas de domínio em português (`usuarios`, `jogos`, `entradas`). Tabelas internas do Laravel com o nome padrão (`sessions`, `cache`, `jobs`).
- Sim/não como `boolean` nativo (diferente do casamento, que usava `'S'`/`'N'`).
- Dinheiro em `decimal(10,2)` com cast `decimal:2`. **Contas sempre em centavos inteiros** via `App\Support\Dinheiro`; nunca somar float. Formatação: `@brl($valor)` → `R$ 1.890`, `@brl($valor, true)` → `+R$ 795` / `−R$ 200`.
- Enums PHP em `app/Enums` para status e tipos; o banco guarda o valor string.
- Cadastro de jogador: nome obrigatório; apelido, telefone e foto opcionais. Não pedir CPF, endereço ou data de nascimento.
- Textos da interface em português do Brasil; termos de poker ficam como o grupo fala (buy-in, rebuy, add-on, rake, prize pool, dealer, blinds, ITM). Ações do torneio em CAIXA ALTA nos botões.

## Front-end

- Design system do clube (tokens, componentes, 25 telas): ver [docs/design-system.md](docs/design-system.md).
- **Referência visual:** `docs/prototipo/rodrigues-prototipo.html` (desktop) e `docs/prototipo/rodrigues-mobile.html` (tablet/celular). Antes de montar uma tela, abra a tela correspondente no protótipo. Tokens, `bundle.css` e fontes em `docs/design-system/`. Onde o protótipo divergir de `docs/regras.md`, vale o `regras.md`.
- SCSS em `resources/scss/site/` (`vars`, `core`, `components`, `pages`), classes com prefixo `rh-`. Tokens como variáveis CSS em `vars/_tokens.scss` (mesmos nomes do `bundle.css`: `--bg-000`, `--radius-md`…); breakpoints com `@include ate(largo|tablet|celular)`. Grades responsivas `rh-grid--2…6` e `rh-grid--side` no lugar de `grid-template-columns` inline. Sem Tailwind e sem WPF3 no site.
- Ícones: SVGs em `resources/svg/site/icons/` (set `rh` do blade-icons, registrado no `AppServiceProvider`): `@svg('rh-home')`.
- Layout base: `<x-site.app titulo="...">` (`app/View/Components/Site/App.php` → `site/layouts/app.blade.php`). Livewire e Alpine vêm num bundle só (`resources/js/site/app.js` importa o ESM do Livewire; o layout usa `@livewireScriptConfig`): não importar Alpine de novo.
- Componentes Blade em `resources/views/components/site/{core,props,poker,window}`.
- A mesa (`<x-site.poker.mesa>`) é renderizada no servidor; `resources/js/site/mesa.js` (Alpine) posiciona os assentos por comprimento de arco e gira em passos de 90°. A rotação fica no `localStorage` do aparelho.
- Fontes Barlow e Barlow Condensed locais em `resources/fonts/barlow/` (sem Google Fonts).
- Entradas do Vite: `resources/scss/site/index.scss`, `resources/js/site/app.js`, `resources/css/filament/painel/theme.css`.

## Testes

- `phpunit.xml` usa `APP_URL=http://localhost` e SQLite em memória. (No casamento, `APP_URL` sem esquema fazia toda rota dar 404 nos testes.)
- A noite de exemplo **Poker Night #18** é a referência: 12 jogadores, 22 entradas, R$ 2.100 arrecadados, rake R$ 210, prize pool R$ 1.890 pago como 945 / 567 / 378. Se a `Calculadora` não devolver isso, algo quebrou.

## Deploy

Por enquanto só local. O lançamento vai para domínio e hospedagem novos; o projeto não depende de WebSocket, fila nem cron, então serve hospedagem compartilhada. Ver [docs/deploy.md](docs/deploy.md).

## Documentação

- [docs/arquitetura.md](docs/arquitetura.md) — stack, estrutura de pastas, rotas e telas
- [docs/regras.md](docs/regras.md) — modelo de dados, acesso e permissões, fases, ações no jogador, cash game, taxas, rankings, modelos, cálculos, acertos, travas e v2 (blinds)
- [docs/painel.md](docs/painel.md) — painel Filament: provider, tema, resources, páginas
- [docs/design-system.md](docs/design-system.md) — onde está o design system e como ele vira código
- [docs/deploy.md](docs/deploy.md) — ambiente local e deploy no lançamento
