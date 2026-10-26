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
| `App\Services\Jogo\Saldos` | Investido, pago, recebido e saldo por jogador (`entradas` + `pagamentos`), em `SaldoDoJogador` (centavos) |
| `App\Services\Jogo\Posicoes` | Posição e prêmio de quem caiu, refeitos a cada eliminação depois de fechar entradas |
| `App\Services\Jogo\Acertos` | Sugestão de "quem paga quem" no fechamento |
| `App\Services\Ranking\Pontuacao` | Pontos por ranking (posição + bônus de mesa final + participação) |
| `App\Actions\Jogo\RegistrarEntrada` | Buy-in (um por jogador), rebuy, add-on e opcionais marcados/obrigatórios, conforme a fase; rebuy traz de volta quem caiu |
| `App\Actions\Jogo\RegistrarPagamento` | Pagamento parcial ou total (jogador pagou / recebeu), também depois de finalizar |
| `App\Actions\Jogo\RegistrarEliminacao` | Provisória na fase de rebuys, definitiva depois; com as entradas fechadas dá a posição na hora |
| `App\Actions\Jogo\RegistrarCashOut` | Cash game: fichas finais × valor da ficha; o jogador sai (registrar de novo corrige) |
| `App\Actions\Jogo\TrocarLugar` | Muda mesa/lugar (quebra de mesa); quem já saiu só guardava o lugar e fica sem lugar |
| `App\Actions\Jogo\DesfazerAcao` | Cancela a última ação do jogador, com motivo (nunca apaga); `ultimaAcao()` diz qual é |
| `App\Actions\Jogo\AgendarJogo`, `CancelarJogo` | Rascunho → agendado (botão no painel) e rascunho/agendado → cancelado |
| `App\Actions\Jogo\ConfirmarJogador`, `DesconfirmarJogador`, `CadastrarJogadorRapido` | Lista da noite (tela 05): confirmar, tirar (só sem lançamento) e cadastro rápido que já confirma |
| `App\Actions\Jogo\SortearLugares`, `AjustarMesa`, `AdicionarMesa`, `RemoverMesa` | Montar as mesas (tela 06): sorteio equilibrado, lugares e dealer, mesas do jogo (a agenda acompanha) |
| `App\Actions\Jogo\IniciarJogo` | Agendado → em andamento (torneio na fase de rebuys); sentados viram "jogando" |
| `App\Actions\Jogo\EncerrarRebuys`, `FecharEntradas` | Rebuys → intervalo (quem caiu e não voltou fica fora de vez) → sem entradas (grava os prêmios e as posições) |
| `App\Actions\Jogo\AtualizarNivel` | Nível e blinds digitados na tela ao vivo (v1; "100/200" vira "100 / 200") |
| `App\Actions\Jogo\FinalizarJogo` | Em andamento → finalizado (fase 4) |
| `App\Services\Jogo\EventoDoJogo` | Cria/atualiza o evento do jogo na agenda (com as mesas); tira modelo, sem data e cancelado |
| `App\Actions\Jogo\CriarDeModelo` | Novo jogo a partir de um torneio modelo (`jogos.e_modelo`) |

Toda ação roda numa transação com `lockForUpdate()` na linha do jogo. Regra violada (ex.: agendar sem buy-in) lança `App\Exceptions\RegraDoJogo`, com mensagem para mostrar como está a quem usa; falta de permissão lança `AuthorizationException`. O trait `App\Actions\Jogo\Concerns\ChecaRegras` dá `permitir()` e `exigirStatus()`. As telas Livewire pegam as duas exceções e mostram a mensagem em `$erro` (sem derrubar a tela).

Regras e fórmulas em [docs/regras.md](docs/regras.md).

### `entradas` e `pagamentos` são o caixa

Cada buy-in, rebuy e add-on é uma linha em `entradas` (o que o jogador deve), com `valor` e `fichas` copiados da opção no momento do registro. Cada pagamento, parcial ou total, é uma linha em `pagamentos` (jogador pagou ao caixa ou recebeu do caixa). Nada é apagado; desfazer preenche `cancelada_em`/`cancelado_em` (trait `App\Models\Concerns\Cancelavel`: use sempre `->validas()` nas somas; `cancelar($quem, $motivo)` só pelo `DesfazerAcao`). Cada jogo define em `jogo_itens` as **entradas** (buy-in, rebuy e add-on padrão + variações como rebuy duplo) e os **opcionais** (opcional, churrasco…, com `destino` `premiacao` / `metade` / `fora`), e em `jogo_taxas` as **taxas** (dealers, casa…), tiradas da premiação antes de dividir. Prize pool = premiação bruta − taxas. Só o Admin do site altera isso durante o jogo, até fechar entradas. Saldo do jogador = investido − prêmio − pago + recebido. Os dois têm `uuid` único vindo do clique, para um toque duplo não registrar duas vezes.

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
- **Permissões** com `spatie/laravel-permission` v6 nos dois guards (`guard_name` separa painel e site). Tabelas renomeadas em `config/permission.php`: `grupos`, `permissoes`, `grupo_permissoes`, `modelo_grupos`, `modelo_permissoes`. Grupos iniciais (seeder): painel Admin, Desenvolvedor, Geral; site Admin, Operador (Operador só lança entradas, pagamentos e eliminações). Formato `recurso.acao` (ex.: `jogos.editar`, `pagamentos.registrar`). Painel: uma policy por model, estendendo `App\Policies\PoliticaDoPainel` (só define `RECURSO`; `forceDelete` é sempre negado). Site: checar no Action e esconder botão com `@can`.
- Painel e site dividem a mesma sessão: cada login só volta para a página pretendida do próprio lado (`App\Filament\Auth\LoginResponse` e `AuthController`).
- Nunca checar permissão comparando nome de grupo; sempre pela permissão.

### Rankings e modelos

- Vários rankings ao mesmo tempo (`rankings`); o jogo (torneio ou cash game) escolhe em quais vale ponto (`jogo_ranking`). Cada ranking configura posições pontuadas, se FT pontua e se participação pontua. Pontos gravados em `ranking_pontos` ao finalizar.
- Torneio modelo = `jogos.e_modelo = true`; nunca aparece no site nem no calendário.

### Blinds e relógio

V2. Na v1, `jogos.nivel_atual` e `blinds_atual` são digitados. Detalhes em [docs/regras.md](docs/regras.md).

## Convenções

- Tabelas e colunas de domínio em português (`usuarios`, `jogos`, `entradas`). Tabelas internas do Laravel com o nome padrão (`sessions`, `cache`, `jobs`).
- Sim/não como `boolean` nativo (diferente do casamento, que usava `'S'`/`'N'`).
- Dinheiro em `decimal(10,2)` com cast `decimal:2`. **Contas sempre em centavos inteiros** via `App\Support\Dinheiro` (`centavos()`, `decimal()`); nunca somar float. Formatação: `@brl($valor)` → `R$ 1.890`, `@brl($valor, true)` → `+R$ 795` / `−R$ 200`; no PHP e nas colunas do Filament, `Dinheiro::brl()`. Centavos só aparecem quando existem (`R$ 12,50`).
- Enums PHP em `app/Enums` para status e tipos; o banco guarda o valor string.
- Cadastro de jogador: nome obrigatório; apelido, telefone e foto opcionais. Não pedir CPF, endereço ou data de nascimento.
- Textos da interface em português do Brasil; termos de poker ficam como o grupo fala (buy-in, rebuy, add-on, rake, prize pool, dealer, blinds, ITM). Ações do torneio em CAIXA ALTA nos botões.
- Traduções: `lang/pt_BR/` (validação, auth, senhas, paginação) e `lang/vendor/filament-*/pt_BR/` só com as chaves que faltam na tradução do Filament. `TraducoesTest` falha se uma atualização do Filament trouxer texto novo sem pt_BR.

## Front-end

- Design system do clube (tokens, componentes, 25 telas): ver [docs/design-system.md](docs/design-system.md).
- **Referência visual:** `docs/prototipo/rodrigues-prototipo.html` (desktop) e `docs/prototipo/rodrigues-mobile.html` (tablet/celular). Antes de montar uma tela, abra a tela correspondente no protótipo. Tokens, `bundle.css` e fontes em `docs/design-system/`. Onde o protótipo divergir de `docs/regras.md`, vale o `regras.md`.
- SCSS em `resources/scss/site/` (`vars`, `core`, `components`, `pages`), classes com prefixo `rh-`. Tokens como variáveis CSS em `vars/_tokens.scss` (mesmos nomes do `bundle.css`: `--bg-000`, `--radius-md`…); breakpoints com `@include ate(largo|tablet|celular)`. Grades responsivas `rh-grid--2…6` e `rh-grid--side` no lugar de `grid-template-columns` inline. Sem Tailwind e sem WPF3 no site.
- Ícones: SVGs em `resources/svg/site/icons/` (set `rh` do blade-icons, registrado no `AppServiceProvider`): `@svg('rh-home')`.
- Layout base: `<x-site.app titulo="...">` (`app/View/Components/Site/App.php` → `site/layouts/app.blade.php`). Livewire e Alpine vêm num bundle só (`resources/js/site/app.js` importa o ESM do Livewire; o layout usa `@livewireScriptConfig`): não importar Alpine de novo.
- Componentes Blade em `resources/views/components/site/{core,props,poker,window}`. Já existem: `props/{brand,field,avatar,datebox,status-jogo,aviso,linha-item}`, `window/{topbar,fluxo-da-noite}`, `poker/mesa`. Conteúdo das telas dentro de `<main class="rh-page"><div class="rh-wrap">`.
- Telas da noite usam `<x-site.operacao :jogo etapa="…" titulo="…">` (topbar com o jogo + fluxo da noite + aviso) e a rota leva `->middleware('jogo.etapa:<etapa>')`. Com `larga`, o conteúdo vai de ponta a ponta (tela ao vivo, que traz a faixa ao vivo dentro do Livewire para atualizar no `wire:poll`).
- Tela ao vivo: Livewire `Jogo\AoVivo` (`?mesa=` escolhe a mesa; sai sozinha se o jogo deixar de estar em andamento). Assentos com total e fichas via a prop `rodapes` da mesa; contagens por jogador em `Services\Jogo\Entradas::porJogador()`; ficha do buy-in (B, 2, 3) em `Jogo::fichaDoBuyin()`.
- Resumo (tela 10): Livewire `Jogo\Resumo` (`?mesa=` e `?pendentes=1` filtram; saldos pelo `Services\Jogo\Saldos`). Clicar na linha abre o mesmo painel do jogador com `:muda-lugar="false"`.
- Painel do jogador: Livewire filho `Jogo\AcoesJogador` (`:key` pelo participante, então o poll do pai não o redesenha). Conversa com o pai por eventos: `caixa-mudou` (lançou algo: pai redesenha), `fechar-jogador` e `mudar-lugar` (pai entra no modo de mudança e o próximo lugar clicado chama o `TrocarLugar`). O `uuid` de cada lançamento nasce ao abrir o modal. Valor digitado com `Dinheiro::lerTexto()` ('1.234,50' → centavos) e `paraTexto()` para preencher o campo.
- Datas no site com `App\Support\Datas` ("Domingo, 04/10/2026", "Dom 04/10/2026", "OUT", "Em 4 dias"); o Carbon já está em pt_BR. Etapas da noite e o destino do Abrir em `App\Support\EtapaDoJogo`.
- A mesa (`<x-site.poker.mesa :jogo-mesa :ocupantes acao="metodoLivewire">`) é renderizada no servidor; `resources/js/site/mesa.js` (Alpine) posiciona os assentos por comprimento de arco e gira em passos de 90°. A rotação fica no `localStorage` do aparelho (`rh-mesa-rotacao-<jogoMesaId>`). Posições em variáveis CSS (`--x0…`, `--y0…`) no contêiner, que tem `wire:ignore.self`: o Livewire redesenha os assentos sem perder a posição. `App\Support\PosicoesDaMesa` faz a mesma conta no PHP (posição de partida). Botão de girar fora da mesa: `$dispatch('girar-mesa', jogoMesaId)`.
- Fontes Barlow e Barlow Condensed locais em `resources/fonts/barlow/` (sem Google Fonts).
- Entradas do Vite: `resources/scss/site/index.scss`, `resources/js/site/app.js`, `resources/css/filament/painel/theme.css`.

## Testes

- `phpunit.xml` usa `APP_URL=http://localhost` e SQLite em memória. (No casamento, `APP_URL` sem esquema fazia toda rota dar 404 nos testes.)
- Em views de componentes Livewire, cada `@if` ganha marcadores `<!--[if BLOCK]>` com quebra de linha: frase que atravessa um `@if` não casa com `assertSee`. Monte o texto numa expressão (`{{ $a ? … : … }}`) ou use `assertSeeText`.
- Depois de um `call()`, o `assertSeeInOrder` do Livewire compara com o JSON da resposta, onde acento e símbolo viram `í`, `−`…: com texto não ASCII, use `assertSee` (que olha o HTML).
- Factory de `Usuario` sorteia apelido: em teste que procura o nome na tela, crie com `'apelido' => null`.
- A noite de exemplo **Poker Night #18** é a referência: 12 jogadores, 22 entradas, R$ 2.100 arrecadados, rake R$ 210, prize pool R$ 1.890 pago como 945 / 567 / 378. Se a `Calculadora` não devolver isso, algo quebrou.
  - `PokerNight18Seeder` cria a noite com os jogadores e lugares do protótipo, em andamento no intervalo (rebuys encerrados, add-ons feitos, ninguém eliminado). O `db:seed` só a cria no ambiente local (testes rodam o seeder dela direto), e rodar de novo não duplica.
  - `PokerNight18Test` confere os números somando direto em centavos; `CalculadoraTest` confere a `Calculadora` contra a mesma noite e contra o exemplo do sábado do `regras.md`.
  - `Jogo\NoiteDeReferenciaTest` joga a mesma noite só pelas Actions (confirmar, sentar, iniciar, entradas, fases, eliminações) e confere números, prêmios, posições e saldos.
- Testes das Actions da noite usam o trait `Tests\Concerns\MontaNoite`: torneio em andamento com entradas padrão de R$ 100, uma mesa, `$admin` e `$operador`, e atalhos `jogador()`, `entrar()`, `pagar()`, `eliminar()`, `desfazer()` e `assertRegra($mensagem, fn)`.
- `Calculadora::doJogo($jogo)` dá os números reais (entradas válidas); `simular($jogo, jogadores, rebuys, addons)` estima pelo cadastro (buy-in, rebuy e add-on padrão + opcionais obrigatórios). Os dois devolvem um `ResumoFinanceiro` em centavos.

## Deploy

Por enquanto só local. O lançamento vai para domínio e hospedagem novos; o projeto não depende de WebSocket, fila nem cron, então serve hospedagem compartilhada. Ver [docs/deploy.md](docs/deploy.md).

## Documentação

- [docs/arquitetura.md](docs/arquitetura.md) — stack, estrutura de pastas, rotas e telas
- [docs/regras.md](docs/regras.md) — modelo de dados, acesso e permissões, fases, ações no jogador, cash game, taxas, rankings, modelos, cálculos, acertos, travas e v2 (blinds)
- [docs/painel.md](docs/painel.md) — painel Filament: provider, tema, resources, páginas
- [docs/design-system.md](docs/design-system.md) — onde está o design system e como ele vira código
- [docs/deploy.md](docs/deploy.md) — ambiente local e deploy no lançamento
