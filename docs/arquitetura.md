# Arquitetura

Um único app Laravel 12 (PHP 8.2), no molde do site do casamento: site de operação em `/` e painel Filament em `/painel`, um banco MySQL. Por enquanto roda só local no Laragon; domínio e hospedagem do lançamento a definir.

## Stack

| Camada | Escolha | Observação |
|---|---|---|
| Linguagem | PHP 8.2 | `config.platform.php = 8.2` no `composer.json` |
| Framework | Laravel 12 | Maior versão compatível com PHP 8.2 (decisão: manter PHP 8.2). Segurança até 24/02/2027 |
| Painel | Filament 5 | Requer PHP 8.2, Laravel 11.28+, Livewire 4, Tailwind 4 (só no tema do painel) |
| Site | Blade components + Livewire 4 + Alpine.js | Livewire já vem com o Filament |
| CSS do site | SCSS (padrão do casamento) + tokens `rh-` | Sem Tailwind no site |
| Build | Vite + `laravel-vite-plugin` | |
| Banco | MySQL | Laragon local; produção a definir |
| PDF | `barryvdh/laravel-dompdf` | Relatórios e fechamento |
| Permissões | `spatie/laravel-permission` v6 | Dois guards (`painel`, `web`), tabelas em português |
| Ícones | `blade-ui-kit/blade-icons` | SVGs do design system em `resources/svg/site/icons` |

Diferenças para o casamento: Laravel 10 → 12, Filament 3 → 5 (API de formulários mudou para Schemas, não copiar resources direto), guard `web` em vez de `guest`, sem Spatie ResponseCache.

## Fluxo

```
Operador (celular/tablet/notebook) ──► Site /         (Blade + Livewire + Alpine)  ─┐
                                                                                    ├─► Actions + Services ─► MySQL
Organizador (PC)                  ──► Painel /painel (Filament 5)                 ─┘
```

Nenhuma das duas portas grava entradas, pagamentos ou muda status direto no model: tudo passa por `App\Actions\Jogo\*`.

## Estrutura de pastas

```
app/
├── Actions/Jogo/               # RegistrarEntrada, RegistrarPagamento, RegistrarEliminacao, RegistrarCashOut,
│                               # TrocarLugar, DesfazerAcao, EncerrarRebuys, FecharEntradas, FinalizarJogo, CriarDeModelo
├── Enums/                      # Modalidade, StatusJogo, FaseTorneio, TipoItem, DestinoItem, CalculoTaxa, MetodoPremiacao, Periodicidade,
│                               # DirecaoPagamento, FormaPagamento, StatusParticipante, TipoEvento, StatusAluguel
├── Filament/
│   ├── Pages/                  # Calendario, Relatorios, Configuracoes
│   ├── Resources/              # Jogo, Modelo, Ranking, Usuario, Seguranca, Grupo, Mesa, Evento, Aluguel, Temporada
│   └── Widgets/                # ProximoJogo, NumerosDoMes, Financeiro, gráficos
├── Helpers/                    # DateHelper, ViewHelper
├── Http/
│   ├── Controllers/            # AuthController, HomeController, JogoController
│   └── Middleware/             # GlobalConfig, JogoEtapa
├── Livewire/Jogo/              # Jogadores, Mesas, AoVivo, AcoesJogador, Resumo, Resultado
├── Models/                     # Seguranca, Usuario, Grupo, Permissao, Mesa, Temporada, Ranking, RankingPontuacao, RankingPonto,
│                               # Configuracao, RegraPremiacao, Jogo, Item, Taxa, Premiacao, JogoMesa,
│                               # Participante, Entrada, Pagamento, Eliminacao, Acerto, Evento, Aluguel
│                               # Concerns/Cancelavel (scopes validas/canceladas)
├── Policies/                   # uma por model, consultando as permissões
├── Providers/Filament/PainelPanelProvider.php
├── Services/
│   ├── Jogo/                   # Calculadora, Saldos, Acertos
│   └── Ranking/                # Pontuacao
├── Support/Dinheiro.php
└── View/Components/Site/       # App, Operacao

resources/
├── views/
│   ├── components/site/
│   │   ├── core/               # panel, stat, data-table, modal, drawer, tabs
│   │   ├── props/              # button, badge, chip, avatar, field
│   │   ├── poker/              # mesa, assento, dealer, ficha, acerto
│   │   └── window/             # topbar, fluxo-da-noite, faixa-ao-vivo
│   ├── livewire/jogo/
│   └── site/{layouts,pages}/
├── scss/site/                  # vars, core, components, pages, wpf
├── css/filament/painel/theme.css
├── fonts/barlow/
├── svg/site/icons/
└── js/site/                    # app.js, mesa.js

config/permission.php           # tabelas do spatie renomeadas (grupos, permissoes...)
database/{migrations,factories,seeders}/  # seeders: permissões, grupos padrão, Poker Night #18
docs/
tests/{Feature,Unit}/
```

## Rotas do site (`routes/web.php`)

Tudo atrás de `auth` (guard `web`, tabela `usuarios`) e das permissões do site, exceto login. As telas 07–09 e 11 são estados da mesma página ao vivo. Torneio e cash game usam as mesmas rotas.

| Tela | Rota | Nome | Implementação |
|---|---|---|---|
| 01 Login | `GET/POST /login`, `POST /logout` | `login`, `logout` | `AuthController` |
| 02 Dashboard | `GET /` | `home` | `HomeController@index` |
| 03 Seleção do jogo | `GET /jogos` | `jogos.index` | `JogoController@index` |
| 04 Conferência | `GET /jogos/{jogo}/conferencia` | `jogos.conferencia` | `JogoController@conferencia` |
| 05 Jogadores confirmados | `GET /jogos/{jogo}/jogadores` | `jogos.jogadores` | Livewire `Jogo\Jogadores` (cadastro rápido incluso) |
| 06 Mesas | `GET /jogos/{jogo}/mesas` | `jogos.mesas` | Livewire `Jogo\Mesas` (lugares, dealer por mesa, floor, Iniciar jogo) |
| 07–09, 11 Ao vivo | `GET /jogos/{jogo}/ao-vivo` | `jogos.ao-vivo` | Livewire `Jogo\AoVivo` + `Jogo\AcoesJogador` (entrada, pagamento, eliminação/cash-out, trocar lugar, desfazer) + botões de fase |
| 10 Resumo | `GET /jogos/{jogo}/resumo` | `jogos.resumo` | Livewire `Jogo\Resumo` (saldos e pendências) |
| 12 Premiação | `GET /jogos/{jogo}/premiacao` | `jogos.premiacao` | `JogoController@premiacao` (só torneio) |
| 13 Resultado | `GET /jogos/{jogo}/resultado` | `jogos.resultado` | Livewire `Jogo\Resultado` (posições / cash-outs) |
| 14 Fechamento | `GET /jogos/{jogo}/fechamento`, `POST /jogos/{jogo}/finalizar` | `jogos.fechamento`, `jogos.finalizar` | `JogoController` |

Middleware `jogo.etapa`: quem abre uma etapa que o status/fase não permite volta para a etapa atual com aviso.

## Painel (`/painel`, guard `painel`, tabela `seguranca`)

| Tela | Onde | Tipo |
|---|---|---|
| 15 Login | `/painel/login` | Login do Filament com as contas de `seguranca` |
| 16 Dashboard | `/painel` | Widgets (com valores pendentes) |
| 17 Calendário | `/painel/calendario` | Página custom (grade `rh-cal` em Blade) |
| 18 Criar/editar evento | modal no calendário | Ação com formulário do `EventoResource` |
| 19 Lista de jogos | `/painel/jogos` | `JogoResource` |
| 20 Criar/editar jogo | `/painel/jogos/{id}/edit` | `JogoResource` (modalidade, entradas, opcionais, taxas, premiação, rankings, mesas, floor) |
| Modelos | `/painel/modelos` | `ModeloResource` + ação Criar a partir do modelo |
| Rankings | `/painel/rankings` | `RankingResource` (regras de pontos e classificação) |
| 21 Usuários | `/painel/usuarios` | `UsuarioResource` |
| 22 Mesas | `/painel/mesas` | `MesaResource` |
| 23 Aluguéis | `/painel/alugueis` | `AluguelResource` |
| 24 Relatórios | `/painel/relatorios` | Página custom com exportação |
| 25 Configurações | `/painel/configuracoes` | Página custom |
| Segurança | `/painel/seguranca` | `SegurancaResource` + `GrupoResource` (contas, grupos e permissões) |

## Tempo real e concorrência

- `wire:poll.5s` na tela ao vivo e no resumo (sem WebSocket em hospedagem compartilhada).
- Ações em transação com `lockForUpdate()` na linha do jogo.
- Botões desabilitados enquanto gravam (`wire:loading.attr="disabled"`) + `uuid` único em `entradas` e `pagamentos`.
- Sem conexão: faixa "Sem conexão" do Livewire; fila offline fora da v1.

## Fases

| Fase | Entrega | Portão |
|---|---|---|
| 0 Base | Laravel 12 + Filament 5, tema, fontes, SCSS do design system, login, docs | |
| 1 Cadastros | Migrations, models, resources básicos do painel, seeder da noite #18 | |
| 2 Antes da noite | Telas 01–06 | |
| 3 Durante a noite | Telas 07–11, ações no jogador (entrada, pagamento, eliminação, cash-out), fases, polling | |
| 4 Final da noite | Telas 12–14, premiação, saldos, acertos, `FinalizarJogo` | Noite #18 inteira pelo site bate R$ 2.100 / 210 / 1.890 |
| 5 Painel completo | Dashboard, calendário, aluguéis, relatórios, rankings, modelos, segurança e permissões, configurações | Testes verdes |
| v2 | Estruturas de blinds e relógio | |
| 6 No ar | Domínio e hospedagem novos, deploy e piloto numa noite real | |
