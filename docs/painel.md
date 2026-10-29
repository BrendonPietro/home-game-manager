# Painel administrativo (Filament 5)

Painel em `/painel` para configurar jogos (torneio e cash game), usuários, contas de segurança, mesas, rankings, modelos, calendário, aluguéis e relatórios. Login com as contas da tabela `seguranca` (guard `painel`); o que cada conta vê e edita vem das permissões dela. A operação da noite acontece no site; o botão **Abrir** de um jogo leva para `jogos.conferencia`.

## Provider (`app/Providers/Filament/PainelPanelProvider.php`)

| Item | Valor |
|---|---|
| id / path | `painel` / `painel` |
| Guard | `painel` (tabela `seguranca`, model `App\Models\Seguranca`) |
| Marca | `Rodrigues Home Game` (marca ♠ provisória) |
| Tema | escuro por padrão (`defaultThemeMode(ThemeMode::Dark)`) |
| `primary` | `#c62f3a` (vermelho carta) |
| `gray` | a partir do grafite `#181b1d` |
| `success` / `warning` / `info` | `#5fcf8f` / `#e7b552` / `#7fb2f0` |
| Fonte | Barlow (local, via tema custom) |
| Tema custom | `resources/css/filament/painel/theme.css` (`php artisan make:filament-theme`), entrada no Vite |

Menu (via `navigationSort`): Dashboard, Calendário, Jogos, Modelos, Estruturas de blinds (45), Rankings, Usuários, Mesas, Aluguéis, Relatórios, Segurança, Configurações. Itens sem permissão `.ver` não aparecem.

## Resources

| Resource | Destaques |
|---|---|
| `JogoResource` | Modalidade (torneio / cash game) no topo. Abas: Informações · Blinds (só torneio: estrutura de blinds e os níveis dela) · Dinheiro (Entradas: buy-in, rebuy e add-on padrão com **+** para variações; Opcionais com destino Não vai / Vai / 50-50; Taxas; simulação do prize pool ao lado) · Premiação (só torneio; valida soma 100%) · Rankings (select múltiplo) · Mesas (dealer por mesa, floor do jogo). Ações: Abrir (site), Duplicar, Salvar como modelo, Ver. Somente leitura quando `finalizado` |

**Jogos, como está (fase 1):**
- Lista só com jogos reais (modelos ficam no `ModeloResource`), com data, modalidade, status, jogadores e arrecadação.
- Entradas em três tabelas (Buy-in, Rebuy, Add-on). A linha padrão de cada uma não se apaga, só se desativa; linha desativada não precisa de valor. Cash game não tem add-on e pede o valor da ficha.
- Opcionais com compra "junto de cada entrada" ou "1 por jogador" e destino Não vai / Vai / 50-50. Taxas com os cinco cálculos (valor fixo, %, valor × quantidade, por jogador, por entrada).
- Premiação: "pela regra do clube" (percentuais definidos ao fechar entradas, pelo número de jogadores) ou manual (percentuais na ordem das posições, somando 100%).
- Mesas: mesa ativa, dealer e lugares; mesa com jogador sentado não sai da lista.
- Blinds (v2): a estrutura de blinds do torneio, com a descrição e a tabela dos níveis logo abaixo do select. Sem estrutura, nível e blinds são digitados na noite. A aba some no cash game (e o `Jogo` limpa a estrutura ao salvar).
- Entradas, opcionais, taxas, premiação, blinds e mesas só se editam em rascunho e agendado; durante o jogo quem mexe é o Admin do site. Finalizado e cancelado abrem só para ver.
- Botões **Agendar** (rascunho → agendado, pela `AgendarJogo`: exige data, um buy-in ativo com valor e premiação manual somando 100%) e **Cancelar jogo** (`CancelarJogo`). Excluir só rascunho sem entradas.
- Salvar cria ou atualiza o evento do jogo na agenda, com as mesas (`Services\Jogo\EventoDoJogo`); cancelado sai da agenda.
- **Duplicar** (nome, padrão "X (cópia)") e **Salvar como modelo** (nome do modelo) no editar e no ver, também de jogo finalizado: copiam a estrutura pelo `Services\Jogo\CopiaDoJogo` e abrem a cópia.
- **Simulação do prize pool** no fim da aba Dinheiro (só com a estrutura aberta): jogadores, rebuys e add-ons (campos que não gravam) e o resultado com o formulário como está, antes de salvar: arrecadação, fora da premiação, cada taxa, prize pool e os prêmios por posição (regra do clube ou manual). Monta itens, taxas e premiação em memória e chama `Calculadora::simular(..., $itens)`; os campos de valor atualizam ao sair do campo.
- Jogo novo (e modelo novo) vem com os padrões das Configurações (`App\Support\PadroesDoJogo`): horário, lugares, mesa final, valor e fichas do buy-in, rebuy e add-on padrão e a taxa padrão em %.

**Modelos, rankings e temporadas (fase 5):**
- `CopiaDoJogo` leva entradas e opcionais, taxas, premiação (só os percentuais), mesas com dealer e lugares, rankings, floor, temporada e regras. Não leva lista, lugares, caixa nem andamento: a cópia nasce em rascunho, sem data (a não ser a que o Criar jogo pede).
- Modelos: mesmo `JogoForm` com `modelo: true` (Nome do modelo no lugar de nome e data, sem status). A permissão é `modelos.*`, não `jogos.*` (o `ModeloResource` sobrescreve `getAuthorizationResponse`); criar o jogo a partir dele pede também `jogos.criar`.
- **Criar jogo** (na lista e no editar do modelo) pede nome, data e horário (o do modelo vem preenchido) e roda o `CriarDeModelo`.
- Rankings: pontos por posição num repeater simples, na ordem (1º, 2º…); bônus de mesa final e de participação. Ranking com pontos gravados não se exclui; mudar as regras não mexe nos pontos já gravados.
- Classificação (`/painel/rankings/{id}/classificacao`, clique na linha da lista): soma por jogador dos pontos dos jogos dentro do período, com jogos, vitórias e melhor posição. Empate: mais vitórias, melhor posição, mais jogos.
- Temporadas: resource simples em Configurações (nome, início, fim, jogos). Temporada com jogo não se exclui.

| Resource | Destaques |
|---|---|
| `ModeloResource` | Jogos com `e_modelo`; mesmo formulário, sem data. Colunas: modelo, modalidade, buy-in, horário, mesas, rankings. Ação Criar jogo (nome, data, horário) |
| `EstruturaBlindsResource` | v2, `/painel/estruturas` (`estruturas.*`, ícone `rh-coins`, depois de Modelos). Nome (único), descrição, ativo e os níveis numa tabela (intervalo, small, big, ante, minutos, nome do intervalo; arrastar reordena). Embaixo, **Como fica**: a prévia numerada (intervalo não conta) com a duração e quando cada nível começa, atualizada antes de salvar. Lista com o resumo ("16 níveis de 15 min · 1 intervalo · 4h15") e quantos jogos usam; **Duplicar** copia com os níveis; estrutura em uso não se exclui. Título "Estruturas de blinds" (o resource sobrescreve o title case do Filament) |
| `RankingResource` | Nome, periodicidade, período, ativo, pontos por posição (repeater), FT pontua? + pontos, participação pontua? + pontos. Página de classificação |
| `UsuarioResource` | Seções Dados (foto, nome, apelido, telefone), Na noite ("Pode ser dealer", "Pode ser floor", ativo) e Acesso ao site (e-mail em minúsculas, senha, grupos do site), mais observações. A seção de acesso só aparece para quem tem `usuarios.editar`: o grupo Geral cadastra jogadores, mas não dá acesso ao site. Excluir é soft delete (filtro de excluídos + Restaurar). Colunas: jogos, pontos e **saldo em aberto** (soma dos saldos nos jogos finalizados, em SQL pelo scope `Usuario::comSaldoEmAberto()`, ordenável; positivo = deve ao caixa; só com `financeiro.ver`) |
| `SegurancaResource` | `/painel/seguranca`, resource simples (modal). Contas do painel: nome, usuário (minúsculo; letras, números, ponto, traço, sublinhado), senha (em branco mantém), ativo (a própria conta não se desativa), grupos e permissões avulsas do painel, último acesso. **Ninguém dá o que não tem** (`App\Support\Permissoes::abrange`): só aparecem os grupos e permissões que a conta logada cobre, e a `SegurancaPolicy` não deixa editar nem excluir conta com mais poder (Admin não mexe no Desenvolvedor) nem excluir a própria |
| `GrupoResource` | Em Configurações (`grupos.*`, só o Desenvolvedor). Abas Todos · Painel · Site. Nome, lado (painel ou site, fixo depois de criar) e a grade de permissões (`Permissoes::opcoes`): no painel uma linha por cadastro (ver, criar, editar, excluir) e depois relatórios, financeiro e configurações; no site, a lista do que se faz na noite. Grava pelo `syncPermissions` (limpa o cache do spatie). Quem entra no grupo se escolhe na conta (Segurança) ou no usuário (Usuários); grupo com gente não se exclui |
| `MesaResource` | Capacidade, valor de aluguel, ativa, observações |
| `AluguelResource` | Tela 23. Totais por status (seguem o filtro de período), abas por status, filtro de período, Marcar pago (grava `pago_em`), Novo aluguel e Editar com o `EventoForm`; excluir apaga o evento (sai do calendário) |
| `TemporadaResource` | Em Configurações |

Cada resource tem policy consultando `recurso.acao`; o Filament esconde menu, botões e ações sem permissão. As policies estendem `App\Policies\PoliticaDoPainel`, que traduz as ações do Filament (`viewAny`/`view` → `ver`, `create` → `criar`, `update` → `editar`, `delete`/`restore` → `excluir`) e nunca permite exclusão definitiva. Restrições extras ficam na policy do model (ex.: `MesaPolicy` não exclui mesa usada em jogo). Booleanos nativos: `Toggle`/`Checkbox` direto.

Convenções dos resources:
- Ícones do menu do set `rh` do design system (`'rh-table'`, `'rh-users'`…), como no AdminShell do protótipo.
- `navigationSort` de 10 em 10 na ordem do menu acima (Jogos 30, Usuários 60, Mesas 70…).
- Dinheiro nas colunas com `Dinheiro::brl()`; nos campos, `numeric` com prefixo `R$`.
- Cadastros pequenos (mesas) são resources simples (`--simple`): uma página, criar e editar em modal.

## Widgets do dashboard

Página `App\Filament\Pages\Dashboard` (tela 16; substitui o Dashboard padrão e o `AccountWidget`): subtítulo com o mês, botão **Novo jogo**, grade de 3 colunas (2 no tablet, 1 no celular). Os números vêm do `App\Services\Painel\Indicadores` (centavos, pela `Calculadora` e pelos `Saldos`). Os widgets são Blade com as classes do design system no tema (`rh-stat`, `rh-datebox`, `rh-bars`, `rh-li`), sem Chart.js, e carregam junto com a página (`$isLazy = false`).

- `VisaoGeral` (largura toda):
  - Próximo jogo num cartão de feltro: o em andamento ou o próximo agendado, com data, confirmados, mesas, Editar (ou Ver) e Abrir no site. Sem jogo: "Nenhum jogo agendado" + Novo jogo.
  - Jogadores cadastrados (ativos · inativos), jogos no mês (agendados, em andamento e finalizados; quantos hoje ou finalizados) e mesas disponíveis (ativas / total, quais estão inativas).
  - Com `financeiro.ver`: arrecadação e prize pools do mês (jogos em andamento ou finalizados), aluguéis pagos no mês e **valores pendentes**: o que os jogadores devem nos jogos finalizados + aluguéis reservados ou confirmados cuja data já passou, e quanto o caixa deve. O "ver" abre a lista (jogo, jogador, quanto, link para o fechamento; aluguéis com link para a tela de aluguéis).
- `ArrecadacaoPorJogo` e `PrizePoolPorJogo` (`financeiro.ver`), `JogadoresPorJogo` e `JogosPorMes` (`jogos.ver`): base `GraficoDeBarras`. Últimos 8 jogos finalizados (rótulo "#18" pelo nome, ou a data) e os últimos 6 meses. Barras `felt-300`, a última em `red-500`, valor em cima ("2,1k") e dica com o nome e o valor.
- `Proximos` (`eventos.ver`): agenda de hoje em diante (7 itens), com data, tipo, horário, mesas e "rascunho"; cada linha abre o mês no calendário. Ocupa duas linhas à direita no desktop.

## Páginas custom

- **Calendário** (`App\Filament\Pages\Calendario`, `/painel/calendario?mes=2026-10`) — grade mensal `rh-cal` em Blade (domingo a sábado, com os dias vizinhos), navegação por mês e Hoje, legenda por tipo. Sem plugin de calendário. Estilos `rh-cal`, `rh-ev`, `rh-dot`, `rh-legend`, `rh-kv` no tema do painel (claro e escuro).
  - Clicar num dia abre **Novo evento** com a data; clicar num aluguel, reserva ou bloqueio abre o editar (com Excluir). O jogo, e qualquer evento para quem só vê, abre o resumo (status, confirmados, buy-in; Ver no painel e Abrir no site).
  - **Novo evento** (tela 18): `App\Filament\Schemas\EventoForm`, o mesmo da tela de aluguéis. Tipo (aluguel de mesa, reserva, bloqueio), cliente (aluguel) ou nome, data, horário inicial e final (vazio = dia todo; pode passar da meia-noite), mesas, responsável, valor e status do aluguel (o valor sugere a soma do aluguel das mesas), observações. Grava pelo `Actions\Agenda\SalvarEvento`.
  - Aviso de mesa ocupada (`Services\Agenda\Conflitos`): mostra se a mesa está livre ou com quem ela já está nesse horário. Só avisa, não impede.
  - Jogo não se cria aqui: o botão **Novo jogo** leva ao cadastro, e o evento do jogo vem do `EventoDoJogo`. Jogo em rascunho aparece tracejado; aluguel cancelado sai da grade.
  - Permissões: calendário com `eventos.*` (o Geral só vê), tela de aluguéis com `alugueis.*`.
- **Relatórios** (`App\Filament\Pages\Relatorios`, `/painel/relatorios?relatorio=premiacoes`, tela 24; `relatorios.ver`) — as contas ficam no `App\Services\Relatorios\Relatorios`, que devolve um `Relatorio` (colunas, linhas cruas em centavos e cartões de resumo); a tela e os arquivos só formatam.
  - Filtros (`Services\Relatorios\Filtro`): período (temporadas, este mês, mês passado, últimos 30 dias, este ano, tudo ou personalizado; padrão = temporada de hoje, senão o ano), jogo e jogador. O período vale pela data do jogo ou do aluguel.
  - Seis relatórios, escolhidos nos cartões: **Jogos** (todos menos rascunho: status, confirmados, jogadores com buy-in, entradas, mesas, arrecadado), **Entradas** (buy-ins, rebuys, add-ons e opcionais por jogo; com jogador, só as dele), **Prize pools** (arrecadado, taxas, fora da premiação e prize pool), **Premiações** (só finalizados; por jogador, do melhor resultado para o pior, com ITM; com jogador escolhido, uma linha por jogo com a posição), **Aluguéis** (pago, a receber e cancelado) e **Arrecadação** (por mês: arrecadado, prize pools, taxas, fora da premiação, aluguéis pagos e o que ficou fora dos prêmios). Entradas, prize pools e arrecadação contam jogos em andamento e finalizados. Aluguéis e arrecadação não usam os filtros de jogo e jogador (a tela avisa).
  - Exportar CSV e Excel (`relatorios.exportar`, `Services\Relatorios\Exportacao`): o CSV sai no jeito do Excel em português (`;`, "1890,00", BOM UTF-8); o .xlsx usa o OpenSpout (vem com o Filament), com dinheiro como número em R$ e data como data. Arquivo `relatorio-<tipo>-<data>`.
  - **Exportar PDF** sem dompdf: abre em outra aba a página para imprimir (`Http\Controllers\Painel\ImprimirRelatorio`, rota autenticada do painel `filament.painel.relatorios.imprimir`, `relatorios.exportar`), com o mesmo relatório e filtros, em tela clara (fontes Barlow locais), e já chama o diálogo do navegador (`imprimir=1`): o "Salvar como PDF" é do próprio navegador. A4 em pé, ou deitado quando o relatório tem mais de 6 colunas; o cabeçalho da tabela se repete em cada página.
- **Configurações** (`App\Filament\Pages\Configuracoes`, `/painel/configuracoes`, menu "Padrões e premiação"; `configuracoes.editar`, tela 25):
  - **Padrões do jogo** (chave `jogo.padroes` em `configuracoes`, `App\Support\PadroesDoJogo`): horário, lugares por mesa, mesa final, valor e fichas do buy-in, rebuy e add-on padrão e uma taxa padrão em % (ex.: Casa 10%). Valem ao criar jogo ou modelo; os que existem não mudam.
  - **Premiação pela regra do clube** (`regras_premiacao`): faixas de jogadores (de, até) com os percentuais "50 / 30 / 20". Confere ao salvar: começa em 1 ou 2, sem buraco nem sobreposição, só a última sem limite, percentuais positivos somando 100% e no máximo um prêmio por jogador da faixa. Vale ao fechar as entradas; quem já fechou não muda.
  - Ficaram de fora do protótipo: identidade do clube (nome, logo, cor, cidade, fuso, endereço) e as chaves de financeiro e preferências (arredondar prêmios, taxa só sobre buy-ins, confirmar desfazer, acertos automáticos), porque mudariam a marca ou as contas da noite sem regra combinada.
  - Rodar o `RegrasPremiacaoSeeder` de novo volta as três faixas padrão por cima do que foi editado (`updateOrCreate` pela faixa); o `PermissoesSeeder` ressincroniza as permissões dos grupos padrão.

## Deploy do painel

- Subir `vendor/` (Filament + Livewire) e rodar `php artisan filament:assets`.
- O tema custom sai no `npm run build` junto com o site.
