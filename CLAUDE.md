# CLAUDE.md

Orientações para o Claude Code trabalhar neste repositório.

## Projeto

Sistema de gerenciamento do **Rodrigues Home Game**, um home game semanal de poker **presencial** (não é poker online). Substitui a planilha da noite: torneios e cash games, jogadores, mesas, dealers e floor, buy-ins, rebuys, add-ons e variações, opcionais (com o percentual que vai para a premiação), taxas do torneio, pagamentos, prize pool, premiação, acertos, torneios modelo e rankings.

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
php artisan migrate --seed               # migrations + o essencial e as contas do clube (banco limpo)
                                         # DADOS_DE_EXEMPLO=true no .env local: + contas dev/teste e Poker Night #18

# Testes
php artisan test --parallel              # a suíte inteira em ~1,5 min (sem --parallel, ~4,5 min)
php artisan test --filter=CalculadoraTest

# Filament
php artisan make:filament-resource NomeDoModel
php artisan filament:assets              # republicar assets (o composer já roda filament:upgrade após update)

# Site
http://rodrigues-home-game.test/vitrine  # vitrine do design system (só APP_ENV=local)
php artisan icones:gerar                 # favicon e ícones a partir de resources/images/logo.png
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
| `App\Services\Jogo\TimeChip` | Time chip do torneio (`jogos.time_chip_fichas` para quem entra até o fim do nível `time_chip_ate_nivel`): `fichasAgora` diz, pelo relógio ou pelo nível digitado, se o buy-in de agora ganha; o `RegistrarEntrada` grava em `entradas.time_chip` |
| `App\Services\Jogo\Acertos` | Sugestão de "quem paga quem" no fechamento (`Transferencia`; o caixa é o participante nulo) |
| `App\Services\Ranking\Pontuacao` | Pontos por ranking (posição + bônus de mesa final + participação) |
| `App\Actions\Jogo\RegistrarEntrada` | Buy-in (um por jogador), rebuy, add-on e opcionais marcados/obrigatórios, conforme a fase; rebuy traz de volta quem caiu |
| `App\Actions\Jogo\MarcarPagamentoAntecipado` | Quem pagou antes (`participantes.pagou_antes`, marcado à mão no painel ou no site, `pagamentos.antecipado`) ganha as fichas do bônus (`jogos.antecipado_fichas`) no buy-in, em `entradas.antecipado`: ao sentar, ou na hora se já sentou |
| `App\Actions\Jogo\RegistrarPagamento` | Pagamento parcial ou total (jogador pagou / recebeu), também depois de finalizar |
| `App\Actions\Jogo\RegistrarEliminacao` | Provisória na fase de rebuys, definitiva depois; com as entradas fechadas dá a posição na hora |
| `App\Actions\Jogo\RegistrarCashOut` | Cash game: fichas finais × valor da ficha; o jogador sai (registrar de novo corrige) |
| `App\Actions\Jogo\TrocarLugar` | Muda mesa/lugar (quebra de mesa); quem já saiu só guardava o lugar e fica sem lugar |
| `App\Actions\Jogo\DesfazerAcao` | Cancela a última ação do jogador, com motivo (nunca apaga); `ultimaAcao()` diz qual é |
| `App\Actions\Jogo\VoltarFase`, `DesfazerPagamento`, `DesfazerAcerto` | Correções com motivo (`jogo.corrigir`): voltar aos rebuys ou reabrir as entradas; desfazer qualquer pagamento (também depois de finalizar e pelo painel) e o FEITO de um acerto |
| `App\Actions\Jogo\RefazerPeloCadastro` | Jogo em andamento editado no painel (`jogos.andamento`, Admin e Desenvolvedor; o `EditJogo` chama depois de salvar, na mesma transação): entradas já lançadas seguem o valor, as fichas e o % do item, cash-out segue a ficha, e com as entradas fechadas os prêmios (`Posicoes::gravarPremios`) e as posições se refazem. Histórico "Cadastro alterado" |
| `App\Services\Jogo\Historico` | Histórico do jogo (`alteracoes`, model `Alteracao`, enum `TipoAlteracao`): quem (site ou painel) mudou o quê e quando; as Actions gravam na própria transação. Aba Histórico no painel e fim do Fechamento no site |
| `App\Actions\Jogo\AgendarJogo`, `CancelarJogo` | Rascunho → agendado (botão no painel) e rascunho/agendado → cancelado |
| `App\Actions\Jogo\ExcluirJogo` | Excluir pelo painel (`JogoResource::acaoExcluir`, na lista, na edição e no Visualizar): rascunho sem entradas com `jogos.excluir`; qualquer jogo, com tudo o que foi lançado, com `jogos.excluir_tudo` (só o Desenvolvedor, `PermissoesSeeder::SO_DESENVOLVEDOR_PAINEL`). Apaga as entradas antes do jogo (o item tem restrict); o modal avisa o que vai junto (`oQueApaga`) |
| `App\Actions\Jogo\ConfirmarJogador`, `DesconfirmarJogador`, `CadastrarJogadorRapido` | Lista da noite (tela 05 e, no painel, a aba Confirmados do jogo: `ConfirmadosRelationManager`, com o pagamento antecipado): confirmar, tirar (só sem lançamento) e cadastro rápido que já confirma. Aceitam conta do site ou do painel (`ChecaRegras::permitirNosDois`) |
| `App\Actions\Jogo\SortearLugares`, `AjustarMesa`, `AdicionarMesa`, `RemoverMesa` | Montar as mesas (tela 06) e sentar quem chega (tela 07): o `SortearLugares` sorteia quem foi escolhido nas mesas escolhidas, antes ou durante o jogo (mesas equilibradas contando quem fica; o lugar de quem saiu conta como livre; cada lugar passa pelo `TrocarLugar`, então quem chega entra com o buy-in; quem caiu primeiro faz o rebuy), pelo modal **Jogadores** (Livewire `Jogo\Sorteio`, evento `abrir-sorteio`: marcar quem chegou, pôr na lista do cadastro ou jogador novo, mesas e SORTEAR, com onde cada um senta e as fichas a entregar); lugares e dealer, mesas do jogo. A mesa do jogo (`JogoMesa`) tem nome próprio ("Mesa 1", "Mesa 2"…, `AdicionarMesa::proximoNome`) e não usa o cadastro de mesas, que é só dos aluguéis; o jogo não ocupa mesas na agenda. Lugar vazio clicado: "Quem senta aqui" (trait `Livewire\Jogo\Concerns\EscolheQuemSenta`, telas 06 e 07) |
| `App\Actions\Jogo\IniciarJogo` | Agendado → em andamento (torneio na fase de rebuys); sentados viram "jogando" |
| `App\Actions\Jogo\AbrirIntervalo`, `FecharEntradas` | Rebuys → intervalo (abre o add-on; o rebuy continua) → sem entradas (quem caiu e não voltou fica fora de vez; grava os prêmios e as posições) |
| `App\Actions\Jogo\AtualizarNivel` | Nível e blinds digitados na tela ao vivo, só sem estrutura de blinds ("100/200" vira "100 / 200") |
| `App\Services\Jogo\Relogio`, `App\Actions\Jogo\ControlarRelogio` | v2: relógio do torneio com estrutura (estado sem cron, `sincronizar` grava `nivel_atual`/`blinds_atual`); continuar, pausar, próximo, anterior e ± minutos (`relogio.controlar`) |
| `App\Actions\Jogo\DefinirResultado` | Com as entradas fechadas, posição de quem ainda está em jogo (vira eliminações do último para o 2º) |
| `App\Actions\Jogo\FinalizarJogo` | Em andamento → finalizado: confere posições (ou cash-outs), grava acertos e pontos dos rankings |
| `App\Actions\Jogo\MarcarAcerto` | Acerto feito depois de finalizar: vira pagamentos (quem pagou / quem recebeu) e os saldos fecham. Confere os saldos antes; um pagamento lançado depois de finalizar refaz os acertos em aberto (`Acertos::refazerEmAberto`), sem cobrar duas vezes |
| `App\Services\Jogo\EventoDoJogo` | Cria/atualiza o evento do jogo na agenda (com as mesas); tira modelo, sem data e cancelado |
| `App\Actions\Jogo\CriarDeModelo` | Novo jogo a partir de um torneio modelo (`jogos.e_modelo`); o jogo guarda o modelo em `jogos.modelo_id` (tipo de evento), e o Duplicar mantém |
| `App\Actions\Agenda\SalvarEvento` | Aluguel de mesa, reserva e bloqueio do calendário (o jogo entra pelo `EventoDoJogo`), com mesas e, no aluguel, cliente, valor e status |
| `App\Services\Painel\Indicadores`, `Atalhos` | Painel de controle: próximo jogo, ranking em destaque (`Ranking::valendoHoje`, o que distribuiu mais pontos; classificação em `Ranking::classificacao`), caixa do mês, valores pendentes, últimos jogos e agenda; `Atalhos` monta os módulos do menu (só o que a conta abre) com uma linha de contexto em cada tela |
| `App\Services\Relatorios\Metricas` | Métricas do painel (finalizados no período): médias de jogadores, prize pool, arrecadação e gasto por jogador, comparadas por tipo de evento (`jogos.modelo_id`), torneio × cash game e valor do buy-in, e mês a mês |
| `App\Services\Relatorios\Relatorios`, `Exportacao` | Relatórios do painel (jogos, entradas, prize pools, premiações, aluguéis, arrecadação) pelo `Filtro`; CSV e Excel (OpenSpout). PDF = página para imprimir (`Painel\ImprimirRelatorio`), sem dompdf |
| `App\Services\Agenda\Conflitos` | Eventos que já ocupam a mesa no horário (aviso no formulário do evento; não impede) |
| `App\Services\Jogo\CopiaDoJogo` | Copia a estrutura (entradas, opcionais, taxas, premiação, mesas, rankings) para um jogo novo em rascunho: criar de modelo, Duplicar e Salvar como modelo |

Toda ação roda numa transação com `lockForUpdate()` na linha do jogo. Regra violada (ex.: agendar sem buy-in) lança `App\Exceptions\RegraDoJogo`, com mensagem para mostrar como está a quem usa; falta de permissão lança `AuthorizationException`. O trait `App\Actions\Jogo\Concerns\ChecaRegras` dá `permitir()` e `exigirStatus()`. As telas Livewire pegam as duas exceções e mostram a mensagem em `$erro` (sem derrubar a tela).

Regras e fórmulas em [docs/regras.md](docs/regras.md).

### `entradas` e `pagamentos` são o caixa

Cada buy-in, rebuy e add-on é uma linha em `entradas` (o que o jogador deve), com `valor` e `fichas` copiados da opção no momento do registro. Cada pagamento, parcial ou total, é uma linha em `pagamentos` (jogador pagou ao caixa ou recebeu do caixa). Nada é apagado; desfazer preenche `cancelada_em`/`cancelado_em` (trait `App\Models\Concerns\Cancelavel`: use sempre `->validas()` nas somas; `cancelar($quem, $motivo)` só pelo `DesfazerAcao`). Cada jogo define em `jogo_itens` as **entradas** (buy-in, rebuy e add-on padrão + variações como rebuy duplo) e os **opcionais** (opcional, churrasco…); cada linha diz quanto vai para a premiação (`percentual_premiacao`, 0 a 100: atalhos 100 / 50 / 0 % e Outro no painel), e em `jogo_taxas` as **taxas** (dealers, casa…), tiradas da premiação antes de dividir. Prize pool = premiação bruta − taxas. Torneio não tem centavos: a premiação bruta desce para o real, a taxa em % arredonda para o real e os prêmios saem em R$ 5 (ou R$ 1 sem o arredondamento das Configurações). Só o Admin do site altera isso durante o jogo, até fechar entradas. Saldo do jogador = investido − prêmio − pago + recebido. Os dois têm `uuid` único vindo do clique, para um toque duplo não registrar duas vezes.

### Jogo: torneio ou cash game

`jogos.modalidade` = `torneio` ou `cash_game`. Status: `rascunho` → `agendado` → `em_andamento` (Iniciar jogo) → `finalizado` (Finalizar). `cancelado` só a partir de `rascunho`/`agendado`.

No torneio, `em_andamento` tem fases (`jogos.fase`): `rebuys` (eliminado pode voltar com rebuy) → `intervalo` (ABRIR INTERVALO: abre o add-on e o rebuy continua, para quem quer voltar no intervalo) → `sem_entradas` (FECHAR ENTRADAS: eliminações viram definitivas, grava prêmios; eliminações dão a posição na hora). **Sentar é entrar** no torneio: quem senta ganha o buy-in padrão (ao Iniciar, ou na hora com o jogo em andamento; `RegistrarEntrada::buyinPadrao`), e quem caiu volta pelo VOLTAR do painel do jogador (rebuy). Cada entrada copia as fichas do item, e o painel do jogador e o Resumo mostram as **fichas entregues** somadas (entradas e opcionais válidos + time chip + bônus do pagamento antecipado; `Entradas::porJogador()`); o aviso do lançamento e o de quem senta dizem quanto entregar ("entregar 50.000 fichas (10.000 do time chip, 20.000 do pagamento antecipado)", `App\Support\Fichas::entregar`). No cash game não há eliminação: o jogador faz cash-out (fichas finais × valor da ficha).

O middleware `jogo.etapa` redireciona para a etapa atual quando alguém abre uma tela que o status/fase ainda não permite. Página que não existe (jogo excluído, link antigo, digitado errado) não mostra 404: volta para a home do site ou para o Painel de controle, com o aviso "Esse jogo não existe mais." (`App\Support\PaginaQueNaoExiste`, registrado no `bootstrap/app.php`); arquivo, JSON e pedido que não é GET continuam no 404, e o link do relógio tem a própria página. No site, o Livewire que recebe 404 (jogo excluído com a tela aberta) recarrega a página (`conexao.js`).

### Tempo real

Hospedagem compartilhada não roda WebSocket. A tela ao vivo e o resumo usam `wire:poll.5s`. Não adicionar Reverb/Pusher sem mudar a hospedagem.

### Acesso: dois logins e permissões

- **Painel** → guard `painel`, tabela `seguranca` (model `App\Models\Seguranca`), `->authGuard('painel')` no provider do Filament.
- **Site** → guard `web`, tabela `usuarios` (model `App\Models\Usuario`). `usuarios` guarda todas as pessoas, inclusive jogadores sem login (`acessa_site = false`). Jogos, pontos e dinheiro se ligam ao `id` daqui. Checkboxes `pode_ser_dealer` e `pode_ser_floor` alimentam os selects de dealer e floor. Login do site (`AuthController`, `/login`) pelo **usuário ou e-mail** num campo só (com `@` procura em `email`, sem, em `usuarios.login`; os dois gravados em minúsculas, então "FulanoDeTal" entra), só com `acessa_site` e `ativo`; 5 tentativas erradas por login+IP bloqueiam por 1 minuto. Conta do site precisa de usuário ou e-mail (Usuários do site). Sessão de 12 h parada (`SESSION_LIFETIME=720`). O site é só para o torneio (não tem Minha conta: a senha do site muda no painel, em Usuários do site). O middleware `site.acesso` (`App\Http\Middleware\AcessoDoSite`, persistente no Livewire) tira na hora a conta desativada e, sem `site.fora_da_noite` (o Operador), só mostra o torneio dentro da janela dele (`App\Support\JanelaDoSite`): de 3 h antes do início até 3 h depois do término previsto (`jogos.horario_fim`, "Término previsto" no cadastro; antes do início = dia seguinte; vazio = início + 12 h); em andamento, até finalizar. Fora da janela a pessoa continua logada em `/sem-torneio` ("Nenhum torneio agora", com quando o próximo abre).
- Formas de pagamento nas telas: só Pix e Dinheiro (`FormaPagamento::disponiveis()`); Cartão fica comentado.
- **Contas do clube** (`config/clube.php`, `ContasDoClubeSeeder`, em qualquer ambiente): `CONTA_DESENVOLVEDOR` (grupo Desenvolvedor no painel e no site) e `CONTAS_ADMIN` (Admin nos dois), no `.env` como `"Nome <usuário>, …"` (o usuário é um nome, como `FulanoDeTal`, ou um e-mail), com `CONTA_DESENVOLVEDOR_SENHA` / `CONTAS_ADMIN_SENHA` de senha inicial. Cada pessoa ganha os dois logins com o mesmo usuário (o login do painel também grava em minúsculas e não diferencia). Rodar o seed de novo não mexe em quem já existe. Senhas e contas pessoais só no `.env`, nunca no repositório. Cada um troca a senha do painel em **Perfil** (`App\Filament\Auth\Perfil`); a do site muda no painel, em Usuários do site.
- **Banco limpo**: o `db:seed` cria só permissões, regras de premiação, estruturas de blinds e as contas do clube; é o banco de verdade (o mesmo que vai para produção). Os dados de exemplo só entram no ambiente local com `DADOS_DE_EXEMPLO=true` (`config('clube.dados_de_exemplo')`; o `phpunit.xml` zera as contas do clube e os exemplos).
- Contas de desenvolvimento dos dados de exemplo (local + `DADOS_DE_EXEMPLO=true`): painel `dev` (`PAINEL_SENHA_INICIAL`) e site `dev@rodrigues.test`, grupo Admin (`SITE_SENHA_INICIAL`). Sem a variável, a senha é gerada e mostrada no terminal. O `ContasDeTesteSeeder` cria também `operador@rodrigues.test` (Operador) e o painel `geral` (Geral), com a senha da conta `dev` de cada lado.
- **Permissões** com `spatie/laravel-permission` v6 nos dois guards (`guard_name` separa painel e site). Tabelas renomeadas em `config/permission.php`: `grupos`, `permissoes`, `grupo_permissoes`, `modelo_grupos`, `modelo_permissoes`. Grupos iniciais (seeder): painel Admin, Desenvolvedor, Geral; site Desenvolvedor (tudo), Admin, Operador (Operador só lança entradas, pagamentos e eliminações). Formato `recurso.acao` (ex.: `jogos.editar`, `pagamentos.registrar`). Painel: uma policy por model, estendendo `App\Policies\PoliticaDoPainel` (só define `RECURSO`; `forceDelete` é sempre negado). Site: checar no Action e esconder botão com `@can`.
- Painel e site dividem a mesma sessão: cada login só volta para a página pretendida do próprio lado (`App\Filament\Auth\LoginResponse` e `AuthController`).
- Nunca checar permissão comparando nome de grupo; sempre pela permissão.
- Ninguém dá o que não tem: contas do painel (`SegurancaResource`) só oferecem grupos/permissões que a conta logada cobre (`App\Support\Permissoes::abrange`), e a `SegurancaPolicy` barra editar ou excluir conta mais forte. Grupos (`GrupoResource`, só `grupos.*`) gravam pelo `syncPermissions` para limpar o cache do spatie.
- Padrões de um jogo novo e regras de premiação do clube: Configurações (`App\Filament\Pages\Configuracoes`, `App\Support\PadroesDoJogo`, `regras_premiacao`).
- Menu do painel em módulos (`navigationGroups` no provider): Painel de controle · Cadastros (Jogos, Modelos, Jogadores, Mesas, Blinds, Rankings; Temporadas fora por enquanto, `TemporadaResource::NO_PAINEL`) · Agenda · Dashboards e relatórios (Métricas, Relatórios) · Sistema (Padrões e premiação, Usuários do site, Contas do painel, Grupos). Jogadores (`UsuarioResource`, `usuarios.*`) e Usuários do site (`UsuarioDoSiteResource`, só `acessa_site`, permissão `acessos.*`) são a mesma tabela `usuarios`.

### Rankings e modelos

- Vários rankings ao mesmo tempo (`rankings`); o jogo (torneio ou cash game) escolhe em quais vale ponto (`jogo_ranking`). Cada ranking configura posições pontuadas, se FT pontua e se participação pontua. Pontos gravados em `ranking_pontos` ao finalizar.
- Torneio modelo = `jogos.e_modelo = true`; nunca aparece no site nem no calendário. No painel fica no `ModeloResource` (mesmo `JogoForm` com `modelo: true`), com permissão `modelos.*` em vez de `jogos.*` (sobrescreve `getAuthorizationResponse`).

### Blinds e relógio

V2 (feita em 28/10/2026). Estruturas de blinds (`EstruturaBlinds` com os `EstruturaNivel` em ordem, a partir de 1; intervalo é um nível sem blinds que não conta no número) cadastradas no painel (menu **Blinds**, `EstruturaBlindsResource`, `estruturas.*`; v3: **Gerar próximos níveis** pelo `App\Support\GeradorDeNiveis`, Duplicar pelo `EstruturaBlinds::duplicar()`, cartões no celular pelo CSS `.rh-niveis`) e escolhidas na aba Blinds do torneio (`jogos.estrutura_blinds_id`; cash game não tem, o `Jogo` limpa ao salvar). `EstruturaBlinds::linhas()` numera os níveis e diz quando cada um começa (prévia, aba Blinds, conferência). Sem estrutura, `jogos.nivel_atual` e `blinds_atual` continuam digitados na tela ao vivo (v1). Com estrutura, o relógio (`Services\Jogo\Relogio`) manda: guarda nível, segundos e `relogio_desde` (nulo = pausado) e calcula o agora andando pelos níveis, sem cron; quem lê o nível para gravar (eliminação, resultado) chama `avancar` com o jogo travado, e as telas chamam `sincronizar`. Iniciar o jogo deixa o nível 1 parado. Na tela ao vivo, o tempo conta no navegador: v3, o motor `relogioTorneio` (`resources/js/site/relogio-torneio.js`) recebe no `data-relogio` o pacote `EstadoDoRelogio::paraNavegador()` (estado + o que mostrar em cada nível) e passa de nível sozinho, também sem internet; o poll acerta a conta. `<x-site.poker.relogio>` (tempo) e `<x-site.poker.painel-relogio>` (bloco grande da TV) leem o motor com `wire:ignore`; ele avisa a troca de nível (`relogio-trocou`) uma vez por nível. Todo estado de componente Alpine aninhado fica declarado no objeto (senão vai para o de fora) e quem tem `relogio.controlar` pausa na faixa e abre o modal com próximo, anterior e ± minutos; no intervalo sem o intervalo aberto, a tela sugere ABRIR INTERVALO, e depois dele, FECHAR ENTRADAS. v3: a TV é o **link do relógio** do jogo (`/relogio/{codigo}`, `jogos.relogio_codigo` criado com o jogo; `RelogioPublicoController`, **sem login** e só para ver): só o relógio (nível, tempo, blinds e ante, o que vem depois, intervalo em tempo e níveis), nada de dinheiro nem jogadores; som na troca de nível, tela cheia e tela acesa (`resources/js/site/tv.js`, Web Audio). A página carrega uma vez e o motor busca `/relogio/{codigo}/estado` a cada 2 s, rota **sem sessão, cookie nem CSRF** (no Laravel 13 o CSRF é o `PreventRequestForgery`); recarrega quando a `chave` muda. O link sai do botão TV da tela ao vivo e da Conferência; GERAR OUTRO LINK (`Actions\Jogo\TrocarLinkDoRelogio`, `relogio.controlar`) derruba o antigo; `/jogos/{jogo}/tv` leva para o link. Copiar com `$copiar` (`resources/js/site/copiar.js`, funciona no http da rede local). Tela do relógio de quem controla (`/jogos/{jogo}/relogio`, Livewire `Jogo\ControleDoRelogio`, botão RELÓGIO da tela ao vivo): relógio grande e botões grandes (anterior, pausar, próximo, ± min) e o **AVISO** (`Actions\Jogo\DispararAviso`: `jogos.avisos` + `aviso_em`; o motor toca nas telas do link quando o número sobe e o aviso tem até 30 s, e pisca "AVISO"; sons em `resources/js/site/som.js`, `$tocar` no clique). O Operador vê sem os botões. Detalhes em [docs/regras.md](docs/regras.md).

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
- Regras do visual (pente-fino unslop, 11/2026; detalhes em [docs/design-system.md](docs/design-system.md#regras-do-visual-valem-para-tela-nova)):
  - CAIXA ALTA só nos botões de ação (`.rh-btn--lg`), nos cartões BUY-IN / REBUY / ADD-ON e na marca. Rótulos, badges, abas e cabeçalhos ficam com só a primeira letra maiúscula, na Barlow e sem `letter-spacing`.
  - Pesos 400 e 500, com 600 raro e nunca 700. Espaçamento na grade de 4px. Raios 4 / 8 / 12.
  - Cor de estado no ponto ou no ícone, não na borda. Feltro só onde há mesa.
  - Transições com `--ease` / `--dur-*`, só de cor, fundo, borda, opacidade e `transform`.
  - Sem `!` e sem ponto final em texto curto.
- **Referência visual:** `docs/prototipo/rodrigues-prototipo.html` (desktop) e `docs/prototipo/rodrigues-mobile.html` (tablet/celular). Antes de montar uma tela, abra a tela correspondente no protótipo. Tokens, `bundle.css` e fontes em `docs/design-system/`. Onde o protótipo divergir de `docs/regras.md`, vale o `regras.md`.
- SCSS em `resources/scss/site/` (`vars`, `core`, `components`, `pages`), classes com prefixo `rh-`. Tokens como variáveis CSS em `vars/_tokens.scss` (mesmos nomes do `bundle.css`: `--bg-000`, `--radius-md`…); breakpoints com `@include ate(largo|tablet|celular)`. Grades responsivas `rh-grid--2…6` e `rh-grid--side` no lugar de `grid-template-columns` inline. Sem Tailwind e sem WPF3 no site.
- Ícones: **todos** (site e painel) em `resources/svg/icones/`, com nome pela ideia (`editar`, `excluir`, `jogadores`, `menu-jogos`…; o `LEIAME.md` da pasta diz onde cada um aparece). Set `rh` do blade-icons (`AppServiceProvider`): `@svg('rh-inicio')` no site, `'rh-…'` no painel; os ícones do próprio Filament apontam para a pasta pelo `App\Support\IconesDoPainel` (`FilamentIcon::register`). Cada item do menu tem o seu `menu-*.svg`. O traço ou preenchimento fica no arquivo (o `.rh-ic` só dá tamanho), então trocar o arquivo por qualquer SVG com o mesmo nome troca o ícone; o `IconesTest` confere que todo ícone usado tem arquivo.
- Topbar do site (`<x-site.window.topbar>`): marca, o jogo (o nome ganha reticências; a data some abaixo de 900px, então a barra nunca quebra), a ficha de quem está logado e o menu (☰) com Jogos, **Acessar o painel** (outra aba; só para quem tem conta no painel com o mesmo usuário ou e-mail, `Usuario::temContaNoPainel()`, ou já entrou nele) e Sair. Listas de escolher jogador (Quem senta aqui, modal Jogadores) mostram `nome_com_apelido` ("Ricardo Alves (RICO)").
- Layout base: `<x-site.app titulo="...">` (`app/View/Components/Site/App.php` → `site/layouts/app.blade.php`). Livewire e Alpine vêm num bundle só (`resources/js/site/app.js` importa o ESM do Livewire; o layout usa `@livewireScriptConfig`): não importar Alpine de novo.
- Componentes Blade em `resources/views/components/site/{core,props,poker,window}`. Já existem: `props/{brand,field,avatar,datebox,status-jogo,aviso,linha-item}`, `window/{topbar,fluxo-da-noite}`, `poker/{mesa,relogio}`. Conteúdo das telas dentro de `<main class="rh-page"><div class="rh-wrap">`.
- Telas da noite usam `<x-site.operacao :jogo etapa="…" titulo="…">` (topbar com o jogo + fluxo da noite + aviso) e a rota leva `->middleware('jogo.etapa:<etapa>')`. Com `larga`, o conteúdo vai de ponta a ponta (tela ao vivo, que traz a faixa ao vivo dentro do Livewire para atualizar no `wire:poll`).
- Tela ao vivo: Livewire `Jogo\AoVivo` (`?mesa=` escolhe a mesa; sai sozinha se o jogo deixar de estar em andamento). Quem está sem lugar não fica embaixo da mesa: o botão JOGADORES ("26 sem lugar", só com `mesas.organizar`) abre o modal `Jogo\Sorteio`, que é um componente próprio (o poll da tela não o redesenha) e avisa a tela com `caixa-mudou`. Assentos com total e fichas via a prop `rodapes` da mesa; contagens por jogador em `Services\Jogo\Entradas::porJogador()`; ficha do buy-in (B, 2, 3) em `Jogo::fichaDoBuyin()`.
- Fechamento (tela 14): Livewire `Jogo\Fechamento`. Antes de finalizar mostra a sugestão do `Acertos`; depois, os acertos gravados com FEITO. O Finalizar recarrega a página (a topbar e o fluxo da noite ficam fora do Livewire).
- Resultado (tela 13): Livewire `Jogo\Resultado`; o select de posição só existe para quem está em jogo e grava pelo `DefinirResultado`.
- Resumo (tela 10): Livewire `Jogo\Resumo` (`?mesa=` e `?pendentes=1` filtram; saldos pelo `Services\Jogo\Saldos`). Clicar na linha abre o mesmo painel do jogador com `:muda-lugar="false"`.
- Painel do jogador: Livewire filho `Jogo\AcoesJogador` (`:key` pelo participante, então o poll do pai não o redesenha). Conversa com o pai por eventos: `caixa-mudou` (lançou algo: pai redesenha), `fechar-jogador` e `mudar-lugar` (pai entra no modo de mudança e o próximo lugar clicado chama o `TrocarLugar`). O `uuid` de cada lançamento nasce ao abrir o modal. Valor digitado com `Dinheiro::lerTexto()` ('1.234,50' → centavos) e `paraTexto()` para preencher o campo.
- Datas no site com `App\Support\Datas` ("Domingo, 04/10/2026", "Dom 04/10/2026", "OUT", "Em 4 dias"); o Carbon já está em pt_BR. Etapas da noite e o destino do Abrir em `App\Support\EtapaDoJogo`.
- A mesa (`<x-site.poker.mesa :jogo-mesa :ocupantes acao="metodoLivewire">`) é renderizada no servidor; `resources/js/site/mesa.js` (Alpine) posiciona os assentos por comprimento de arco e gira em passos de 90°. A rotação fica no `localStorage` do aparelho (`rh-mesa-rotacao-<jogoMesaId>`). Posições em variáveis CSS (`--x0…`, `--y0…`) no contêiner, que tem `wire:ignore.self`: o Livewire redesenha os assentos sem perder a posição. `App\Support\PosicoesDaMesa` faz a mesma conta no PHP (posição de partida). Botão de girar fora da mesa: `$dispatch('girar-mesa', jogoMesaId)`. O trilho tem o recorte do lugar do dealer (máscara no CSS, lado pelo `data-dealer` que o `mesa.js` troca ao girar), e a caixa do dealer fica encaixada nele (CSS, fora da volta dos jogadores e atrás dos lugares).
- Fontes Barlow e Barlow Condensed locais em `resources/fonts/barlow/` (sem Google Fonts), pré-carregadas pelo `<x-fontes />`. Barra de rolagem fina com o espaço reservado (`scrollbar-gutter: stable`) no site e no painel: o topo não pula quando um modal trava a rolagem.
- Painel em **SPA** (`->spa(hasPrefetching: true)`): link escrito à mão no painel usa `\Filament\Support\generate_href_html()`; o site e a impressão ficam fora (`spaUrlExceptions`).
- Favicon e ícones (site e painel) saem do logo `resources/images/logo.png` pelo `php artisan icones:gerar` (Imagick, só no local; os arquivos gerados vão no repositório) e entram pelo `<x-icones />`. Detalhes em [docs/design-system.md](docs/design-system.md#logo-e-favicon).
- Entradas do Vite: `resources/scss/site/index.scss`, `resources/js/site/app.js`, `resources/css/filament/painel/theme.css`.

## Testes

- `phpunit.xml` usa `APP_URL=http://localhost` e SQLite em memória. (No casamento, `APP_URL` sem esquema fazia toda rota dar 404 nos testes.)
- Em views de componentes Livewire, cada `@if` ganha marcadores `<!--[if BLOCK]>` com quebra de linha: frase que atravessa um `@if` não casa com `assertSee`. Monte o texto numa expressão (`{{ $a ? … : … }}`) ou use `assertSeeText`.
- `set('prop.chave', …)` no teste manda a chave aninhada, mas o navegador manda o array inteiro quando ele ainda está vazio: hook `updatedProp($valor, $chave)` tem de aceitar `$chave` nula e `$valor` array (ex.: `Jogo\Resultado::updatedPosicoes`). Teste os dois.
- Depois de um `call()`, o `assertSeeInOrder` do Livewire compara com o JSON da resposta, onde acento e símbolo viram `í`, `−`…: com texto não ASCII, use `assertSee` (que olha o HTML).
- Factory de `Usuario` sorteia apelido: em teste que procura o nome na tela, crie com `'apelido' => null`.
- A noite de exemplo **Poker Night #18** é a referência: 12 jogadores, 22 entradas, R$ 2.100 arrecadados, rake R$ 210, prize pool R$ 1.890 pago como 950 / 565 / 375 (prêmios arredondados para R$ 5, o padrão; sem arredondar, 945 / 567 / 378). Se a `Calculadora` não devolver isso, algo quebrou.
  - `PokerNight18Seeder` cria a noite com os jogadores e lugares do protótipo, em andamento no intervalo (rebuys encerrados, add-ons feitos, ninguém eliminado). O `db:seed` só a cria no ambiente local com `DADOS_DE_EXEMPLO=true` (testes rodam o seeder dela direto), e rodar de novo não duplica.
  - `PokerNight18Test` confere os números somando direto em centavos; `CalculadoraTest` confere a `Calculadora` contra a mesma noite e contra o exemplo do sábado do `regras.md`.
  - `Jogo\NoiteDeReferenciaTest` joga a mesma noite só pelas Actions (confirmar, sentar, iniciar, entradas, fases, eliminações) e confere números, prêmios, posições e saldos.
- Testes das Actions da noite usam o trait `Tests\Concerns\MontaNoite`: torneio em andamento com entradas padrão de R$ 100, uma mesa, `$admin` e `$operador`, e atalhos `jogador()`, `entrar()`, `pagar()`, `eliminar()`, `desfazer()` e `assertRegra($mensagem, fn)`.
- `Calculadora::doJogo($jogo)` dá os números reais (entradas válidas); `simular($jogo, jogadores, rebuys, addons)` estima pelo cadastro (buy-in, rebuy e add-on padrão + opcionais obrigatórios). Os dois devolvem um `ResumoFinanceiro` em centavos. A simulação do cadastro do jogo no painel passa os itens ainda não salvos no 5º parâmetro (e taxas/premiação pelas relações em memória).
- Painel: contas e grupos em `Painel\SegurancaTest`, Configurações em `Painel\ConfiguracoesTest`. Factory de `Seguranca` sorteia login com ponto (`john.doe`): o login do painel aceita ponto.

## Deploy

Por enquanto só local. O lançamento vai para domínio e hospedagem novos; o projeto não depende de WebSocket, fila nem cron, então serve hospedagem compartilhada. Ver [docs/deploy.md](docs/deploy.md).

## Documentação

- [docs/arquitetura.md](docs/arquitetura.md) — stack, estrutura de pastas, rotas e telas
- [docs/regras.md](docs/regras.md) — modelo de dados, acesso e permissões, fases, ações no jogador, cash game, taxas, rankings, modelos, cálculos, acertos, travas e v2 (blinds)
- [docs/painel.md](docs/painel.md) — painel Filament: provider, tema, resources, páginas
- [docs/design-system.md](docs/design-system.md) — onde está o design system e como ele vira código
- [docs/deploy.md](docs/deploy.md) — ambiente local e deploy no lançamento
- [docs/roteiro-de-teste.md](docs/roteiro-de-teste.md) — teste à mão do sistema inteiro, na ordem da noite, e o que se sabe que ainda falta
