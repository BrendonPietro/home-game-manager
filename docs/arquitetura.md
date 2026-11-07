# Arquitetura

Um único app Laravel 13 (PHP 8.5), no molde do site do casamento: site de operação em `/` e painel Filament em `/painel`, um banco MySQL. Por enquanto roda só local no Laragon; domínio e hospedagem do lançamento a definir.

## Stack

| Camada | Escolha | Observação |
|---|---|---|
| Linguagem | PHP 8.5 | `config.platform.php = 8.5` no `composer.json`. A hospedagem do lançamento precisa ter PHP 8.5 |
| Framework | Laravel 13 | Bugs até o 3º tri de 2027, segurança até 17/03/2028. Era Laravel 12 + PHP 8.2 até 22/10/2026 |
| Painel | Filament 5 | Requer PHP 8.2, Laravel 11.28+, Livewire 4, Tailwind 4 (só no tema do painel) |
| Site | Blade components + Livewire 4 + Alpine.js | Livewire já vem com o Filament |
| CSS do site | SCSS (padrão do casamento) + tokens `rh-` | Sem Tailwind no site |
| Build | Vite + `laravel-vite-plugin` | |
| Banco | MySQL | Laragon local; produção a definir |
| PDF | Página para imprimir (Salvar como PDF do navegador) | Relatórios; sem dompdf. CSV e Excel pelo OpenSpout, que vem com o Filament |
| Permissões | `spatie/laravel-permission` v6 | Dois guards (`painel`, `web`), tabelas em português |
| Ícones | `blade-ui-kit/blade-icons` | SVGs do design system em `resources/svg/site/icons` |

Diferenças para o casamento: Laravel 10 → 13, Filament 3 → 5 (API de formulários mudou para Schemas, não copiar resources direto), guard `web` em vez de `guest`, sem Spatie ResponseCache.

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
├── Actions/Agenda/             # SalvarEvento (aluguel, reserva, bloqueio)
├── Actions/Jogo/               # Lista e mesas: ConfirmarJogador, DesconfirmarJogador, CadastrarJogadorRapido, SortearLugares,
│                               #   AjustarMesa, AdicionarMesa, RemoverMesa, TrocarLugar (sentar é entrar no torneio)
│                               # Status e fases: AgendarJogo, CancelarJogo, IniciarJogo, AbrirIntervalo, FecharEntradas,
│                               #   DefinirResultado, FinalizarJogo, CriarDeModelo
│                               # Caixa: RegistrarEntrada, RegistrarPagamento, RegistrarEliminacao, RegistrarCashOut, MarcarAcerto
│                               # Correções (com motivo e histórico): DesfazerAcao, VoltarFase, DesfazerPagamento, DesfazerAcerto
│                               # Relógio: AtualizarNivel, ControlarRelogio, DispararAviso, TrocarLinkDoRelogio
│                               # Concerns/ChecaRegras (permitir, permitirNosDois, exigirStatus)
├── Enums/                      # Modalidade, StatusJogo, FaseTorneio, TipoItem, CalculoTaxa, MetodoPremiacao, Periodicidade,
│                               # DirecaoPagamento, FormaPagamento, StatusParticipante, TipoEvento, StatusAluguel, ComandoRelogio,
│                               # TipoAlteracao (histórico)
├── Filament/
│   ├── Pages/                  # Dashboard (tela 16), Calendario, Relatorios, Configuracoes
│   ├── Schemas/                # EventoForm (calendário e aluguéis)
│   ├── Resources/              # Jogo (abas Confirmados e Histórico), Modelo, EstruturaBlinds, Ranking, Usuario, Seguranca,
│   │                           # Grupo, Mesa, Aluguel, Temporada
│   └── Widgets/                # VisaoGeral, Proximos, GraficoDeBarras (Arrecadacao/PrizePool/JogadoresPorJogo, JogosPorMes)
├── Http/
│   ├── Controllers/            # AuthController, HomeController, JogoController, RelogioPublicoController (link do relógio);
│   │                           # Painel/ImprimirRelatorio (PDF)
│   └── Middleware/             # JogoEtapa, AcessoDoSite (site.acesso)
├── Livewire/Jogo/              # Jogadores, Mesas, AoVivo, AcoesJogador, Resumo, Resultado, Fechamento, ControleDoRelogio
│                               # Concerns/EscolheQuemSenta (lugar vazio: quem senta, telas 06 e 07)
├── Models/                     # Seguranca, Usuario, Grupo, Permissao, Mesa, Temporada, Ranking, RankingPontuacao, RankingPonto,
│                               # Configuracao, RegraPremiacao, Jogo, Item, Taxa, Premiacao, JogoMesa,
│                               # Participante, Entrada, Pagamento, Eliminacao, Acerto, Alteracao (histórico), Evento, Aluguel,
│                               # EstruturaBlinds, EstruturaNivel
│                               # Concerns/Cancelavel (scopes validas/canceladas)
├── Policies/                   # uma por model, consultando as permissões
├── Providers/Filament/PainelPanelProvider.php
├── Services/
│   ├── Agenda/                 # Conflitos (mesa ocupada no horário)
│   ├── Jogo/                   # Calculadora, Saldos, Posicoes, Acertos, Historico, Entradas, EventoDoJogo, CopiaDoJogo,
│   │                           # Relogio + EstadoDoRelogio
│   ├── Painel/                 # Indicadores (números do dashboard)
│   ├── Relatorios/             # Relatorios, Filtro, Relatorio, Coluna, Exportacao (CSV e Excel)
│   └── Ranking/                # Pontuacao
├── Support/                    # Dinheiro, Datas, EtapaDoJogo, PadroesDoJogo (padrões das Configurações), Permissoes (nomes e
│                               # "não dá o que não tem"), ArredondarPremios, GeradorDeNiveis, PosicoesDaMesa
└── View/Components/Site/       # App, Operacao

resources/
├── views/
│   ├── components/site/
│   │   ├── core/               # panel, stat, data-table, modal, drawer, tabs
│   │   ├── props/              # button, badge, chip, avatar, field
│   │   ├── poker/              # mesa, relogio (v2)
│   │   └── window/             # topbar, fluxo-da-noite, faixa-ao-vivo
│   ├── livewire/jogo/
│   └── site/{layouts,pages}/
├── scss/site/                  # vars, core, components, pages
├── css/filament/painel/theme.css
├── fonts/barlow/
├── svg/site/icons/
└── js/site/                    # app.js, mesa.js, relogio-torneio.js (v3: o motor do relógio), tv.js (link do relógio), som.js (campainha e AVISO), tela-acesa.js e copiar.js

config/permission.php           # tabelas do spatie renomeadas (grupos, permissoes...)
database/{migrations,factories,seeders}/  # seeders: permissões, grupos padrão, estruturas de blinds, Poker Night #18
docs/
tests/{Feature,Unit}/
```

## Rotas do site (`routes/web.php`)

Tudo atrás de `auth` (guard `web`, tabela `usuarios`) e das permissões do site, exceto login. As telas 07–09 e 11 são estados da mesma página ao vivo. Torneio e cash game usam as mesmas rotas.

| Tela | Rota | Nome | Implementação |
|---|---|---|---|
| 01 Login | `GET/POST /login`, `POST /logout` | `login`, `logout` | `AuthController` (e-mail e senha; `guest:web` / `auth:web`) |
| Sem torneio | `GET /sem-torneio` | `sem-torneio` | Página sem login para onde vai quem não tem `site.fora_da_noite` quando não há jogo em andamento |
| 02 Dashboard | `GET /` | `home` | `HomeController@index` (jogo ao vivo ou próximo agendado com prize pool real/estimado, números, últimos finalizados, agenda, última noite) |
| 03 Seleção do jogo | `GET /jogos` | `jogos.index` | `JogoController@index` (abas Próximos / Em andamento / Finalizados, busca `?busca=`, destaque do jogo ao vivo ou de hoje) |
| 04 Conferência | `GET /jogos/{jogo}/conferencia` | `jogos.conferencia` | `JogoController@conferencia` |
| 05 Jogadores confirmados | `GET /jogos/{jogo}/jogadores` | `jogos.jogadores` | Livewire `Jogo\Jogadores` (cadastro rápido incluso) |
| 06 Mesas | `GET /jogos/{jogo}/mesas` | `jogos.mesas` | Livewire `Jogo\Mesas` (sem lugar, clicar para mover/trocar, sortear, lugares e dealer por mesa, adicionar/remover mesa, girar, Iniciar jogo). O floor do jogo é escolhido no painel |
| 07–09, 11 Ao vivo | `GET /jogos/{jogo}/ao-vivo` | `jogos.ao-vivo` | Livewire `Jogo\AoVivo` + `Jogo\AcoesJogador` (entrada, pagamento, eliminação/cash-out, trocar lugar, desfazer) + botões de fase |
| 10 Resumo | `GET /jogos/{jogo}/resumo` | `jogos.resumo` | Livewire `Jogo\Resumo` (saldos e pendências) |
| TV | `GET /jogos/{jogo}/tv` | `jogos.tv` | v3: leva para o link do relógio do jogo (a tela da TV da v2, Livewire `Jogo\Tv` com prize pool e premiação, saiu) |
| Relógio do gerenciador (v3) | `GET /jogos/{jogo}/relogio` | `jogos.relogio` | Livewire `Jogo\ControleDoRelogio` (`jogo.etapa:ao-vivo`): relógio grande, ANTERIOR, PAUSAR/CONTINUAR, PRÓXIMO, ± minutos e o AVISO; o Operador só vê |
| Link do relógio (v3) | `GET /relogio/{codigo}` e `GET /relogio/{codigo}/estado` | `relogio.publico`, `relogio.estado` | `RelogioPublicoController`, **sem login**: só o relógio (nível, tempo, blinds, ante, o que vem depois, intervalo em tempo e níveis), som na troca de nível e tela cheia; o estado em JSON a cada 2 s, sem sessão, cookie nem CSRF |
| 12 Premiação | `GET /jogos/{jogo}/premiacao` | `jogos.premiacao` | `JogoController@premiacao` (só torneio) |
| 13 Resultado | `GET /jogos/{jogo}/resultado` | `jogos.resultado` | Livewire `Jogo\Resultado` (posições / cash-outs) |
| 14 Fechamento | `GET /jogos/{jogo}/fechamento` | `jogos.fechamento` | Livewire `Jogo\Fechamento` (conferência, por jogador, acertos, Finalizar e acertos feitos) |

Middleware `jogo.etapa` (`App\Http\Middleware\JogoEtapa`, alias em `bootstrap/app.php`): quem abre uma etapa que o status/fase não permite volta para a etapa atual com aviso (`session('aviso')`, mostrado pelo `<x-site.props.aviso>`). Enquanto a tela da etapa atual não existe, volta para a lista de jogos.

O site só mostra jogos reais agendados, em andamento e finalizados (`Jogo::noSite()`); rascunho e cancelado ficam no painel. `App\Support\EtapaDoJogo` guarda as etapas do fluxo da noite, quais o status permite (agendado: conferência, jogadores, mesas; em andamento: ao vivo e resumo, e também premiação, resultado e fechamento depois de fechar as entradas, ou a qualquer hora no cash game; finalizado: premiação, resultado, fechamento; cash game sem premiação) e para onde o **Abrir** leva. Enquanto a tela da etapa não existe, o Abrir aparece desabilitado.

## Painel (`/painel`, guard `painel`, tabela `seguranca`)

| Tela | Onde | Tipo |
|---|---|---|
| 15 Login | `/painel/login` | Login do Filament com as contas de `seguranca` |
| 16 Dashboard | `/painel` | `App\Filament\Pages\Dashboard` + widgets Blade (próximo jogo, números, valores pendentes, gráficos, próximos) |
| 17 Calendário | `/painel/calendario` | Página custom (grade `rh-cal` em Blade) |
| 18 Criar/editar evento | modal no calendário | Ação com o `Filament\Schemas\EventoForm` (o mesmo dos aluguéis) |
| 19 Lista de jogos | `/painel/jogos` | `JogoResource` |
| 20 Criar/editar jogo | `/painel/jogos/{id}/edit` | `JogoResource` (modalidade, entradas, opcionais, taxas, premiação, rankings, mesas, floor) |
| Modelos | `/painel/modelos` | `ModeloResource` + ação Criar a partir do modelo |
| Blinds | `/painel/estruturas` | `EstruturaBlindsResource` (v2: níveis, intervalos e a prévia; v3: gerar próximos níveis, duplicar no editar, cartões no celular) |
| Rankings | `/painel/rankings` | `RankingResource` (regras de pontos e classificação) |
| 21 Usuários | `/painel/usuarios` | `UsuarioResource` |
| 22 Mesas | `/painel/mesas` | `MesaResource` |
| 23 Aluguéis | `/painel/alugueis` | `AluguelResource` |
| 24 Relatórios | `/painel/relatorios` | `App\Filament\Pages\Relatorios`: filtros, seis relatórios, prévia e exportação CSV/Excel; PDF pela página para imprimir `/painel/relatorios/imprimir` |
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
| 0 Base | Laravel 13 + Filament 5, tema, fontes, SCSS do design system, login, docs | |
| 1 Cadastros | Migrations, models, resources básicos do painel, seeder da noite #18 | |
| 2 Antes da noite | Telas 01–06 | |
| 3 Durante a noite | Telas 07–11, ações no jogador (entrada, pagamento, eliminação, cash-out), fases, polling | |
| 4 Final da noite | Telas 12–14, premiação, saldos, acertos, `FinalizarJogo` | Noite #18 inteira pelo site bate R$ 2.100 / 210 / 1.890 (cumprido em 26/10/2026) |
| 5 Painel completo | Dashboard, calendário, aluguéis, relatórios, rankings, modelos, segurança e permissões, configurações | Testes verdes |
| v2 | Estruturas de blinds e relógio | Etapas 1 (estruturas no painel, no jogo e na conferência), 2 (a regra do relógio), 3 (relógio na tela ao vivo, com os controles e a sugestão de encerrar rebuys) e 4 (tela cheia para a TV) feitas em 28/10/2026 |
| v3 | Relógio no estilo Poker Depot (decisões de 05/11/2026) | Etapas 1 (Blinds: gerar próximos níveis, duplicar no editar, editor no celular), 2 (relógio que anda sem internet), 3 (link do torneio só com o relógio, no lugar da TV) e 4 (tela do relógio do gerenciador com o AVISO) |
| 6 No ar | Domínio e hospedagem novos, deploy e piloto numa noite real | |
