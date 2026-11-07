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
| Fonte | Barlow (local, via tema custom), pré-carregada pelo `<x-fontes />` (render hook `HEAD_START`) |
| Navegação | **SPA** (`->spa(hasPrefetching: true)`): o menu, o topo e os links trocam a tela sem recarregar o painel e já buscam a página no hover. Links escritos à mão usam `\Filament\Support\generate_href_html()` (atalhos e agenda do Painel de controle). Fora do SPA (`spaUrlExceptions`): o site (mesmo domínio: `/`, `/jogos*`, `/relogio*`, `/login`…) e a página de impressão do relatório |
| Tema custom | `resources/css/filament/painel/theme.css` (`php artisan make:filament-theme`), entrada no Vite. Segue as regras do visual do [design-system.md](design-system.md#regras-do-visual-valem-para-tela-nova): raios 4/8/12 (`--radius-md/lg/xl`), títulos e números em 500, rótulos sem caixa alta, ícones com traço 1.5, curva `--rh-ease` |

Menu em módulos (`->navigationGroups()` no provider; cada item com `$navigationGroup` e `$navigationSort`). Itens sem permissão `.ver` não aparecem.

| Módulo | Itens |
|---|---|
| Painel de controle | O dashboard (`Pages\Dashboard`), no topo, sem grupo: próximo jogo e ranking em destaque, caixa do mês, atalhos dos módulos e agenda |
| Cadastros | Jogos, Modelos, Jogadores (`UsuarioResource`, `/painel/jogadores`), Mesas, Blinds, Rankings, Temporadas |
| Agenda | Calendário, Aluguéis |
| Dashboards e relatórios | Métricas, Relatórios |
| Sistema | Padrões e premiação (Configurações), Usuários do site (`UsuarioDoSiteResource`), Contas do painel (`SegurancaResource`), Grupos |

**Jogadores × Usuários do site**: a mesma tabela `usuarios`. Em Cadastros → **Jogadores** ficam todas as pessoas (dados, foto, dealer/floor, ativo; `usuarios.*`, o Geral cadastra). Em Sistema → **Usuários do site** ficam só as que entram no site (`acessa_site`), com e-mail, senha e grupos do site, e a permissão própria `acessos.*` (Admin e Desenvolvedor): **Novo usuário do site**, **Dar acesso a um jogador** (escolhe alguém do cadastro) e **Tirar acesso** (a pessoa continua em Jogadores; sai do site na hora).

## Resources

| Resource | Destaques |
|---|---|
| `JogoResource` | Modalidade (torneio / cash game) no topo. Abas: Informações (com o **Tipo de evento**: o modelo de onde o jogo saiu, `jogos.modelo_id`, que as Métricas usam para comparar) · Blinds (só torneio: estrutura de blinds e os níveis dela) · Dinheiro (Entradas: buy-in, rebuy e add-on padrão com **+** para variações; Opcionais; em cada linha, quanto vai para a premiação (100 / 50 / 0 % ou Outro, digitado); Taxas; simulação do prize pool ao lado) · Premiação (só torneio; na manual, posições fixas com o 1º, 2º… ao lado (contador CSS `.rh-premiacao`), sem arrastar: para mudar, troca o valor; **+** adiciona posição; "Soma 95%: falta 5%" embaixo; duas sugestões pelo número de posições da lista, **Concentrada no 1º** e **Mais equilibrada** (`App\Support\SugestaoDePremiacao`, em múltiplos de 5), que só preenchem os campos; posição sem percentual some ao salvar; fora de 100%, um alerta com OK e não salva: `Pages\Concerns\ConfereAPremiacao`) · Rankings (select múltiplo) · Mesas (nome, dealer e lugares de cada mesa do torneio, floor do jogo; **+** adiciona "Mesa N" com os lugares por mesa do jogo; não usa o cadastro de mesas). Ações: Abrir (site), Duplicar, Salvar como modelo, Ver. Em andamento, Dinheiro, Premiação e Mesas abrem só com `jogos.andamento` (Admin e Desenvolvedor), com um aviso: salvar refaz as entradas já lançadas e os prêmios (`RefazerPeloCadastro`, docs/regras.md); modalidade e blinds ficam travadas. Somente leitura quando `finalizado` |

**Jogos, como está (fase 1):**
- Lista só com jogos reais (modelos ficam no `ModeloResource`), com data, modalidade, status, jogadores e arrecadação.
- Entradas em três tabelas (Buy-in, Rebuy, Add-on). A linha padrão de cada uma não se apaga, só se desativa; linha desativada não precisa de valor. Cash game não tem add-on e pede o valor da ficha.
- Opcionais com compra "junto de cada entrada" ou "1 por jogador" e quanto vai para a premiação: atalhos 100 / 50 / 0 % e Outro (ex.: 75%), também nas entradas (`JogoForm::camposDaPremiacao`, gravado em `percentual_premiacao`). Taxas com os cinco cálculos (valor fixo, %, valor × quantidade, por jogador, por entrada).
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
| `EstruturaBlindsResource` | v2, `/painel/estruturas` (`estruturas.*`, ícone `rh-coins`, depois de Modelos). Menu **Blinds** (o que roda no torneio é o relógio). Nome (único), descrição, ativo e os níveis numa tabela (intervalo, small, big, ante, minutos, nome do intervalo; o número do nível ao lado do arrastar; arrastar reordena; intervalo só mostra nome e minutos). No celular em pé, cada nível vira um cartão de duas linhas ("Nível N", intervalo, arrastar e excluir; small, big, ante e minutos lado a lado), pelo CSS `.rh-niveis` do tema. **Gerar próximos níveis** (embaixo da tabela): quantos, minutos, como o big blind sobe (dobrando, multiplicando ou somando, sugerido pelos últimos níveis) e um intervalo a cada N níveis, com a prévia; os níveis entram no fim da lista e só gravam ao salvar (`App\Support\GeradorDeNiveis`). Embaixo, **Como fica**: a prévia numerada (intervalo não conta) com a duração e quando cada nível começa, atualizada antes de salvar. Lista com o resumo ("16 níveis de 15 min · 1 intervalo · 4h15") e quantos jogos usam; **Duplicar** (na lista e no editar) copia com os níveis (`EstruturaBlinds::duplicar()`: "X (cópia)", "X (cópia 2)"…) e abre a cópia; estrutura em uso não se exclui. Título "Blinds" e "Criar estrutura de blinds" (o resource sobrescreve o title case do Filament) |
| `RankingResource` | Nome, periodicidade, período, ativo, pontos por posição (repeater), FT pontua? + pontos, participação pontua? + pontos. Página de classificação |
| `UsuarioResource` | Cadastros → **Jogadores**: só quem joga, sem nada de acesso ao site (isso fica em Usuários do site). Formulário: Dados (foto, nome, apelido, telefone e os checks "Pode ser dealer", "Pode ser floor" e ativo) e observações; o Geral cadastra. Excluir é soft delete (filtro de excluídos + Restaurar). Colunas: telefone, **Dealer** e **Floor** ("Sim" ou —), jogos, pontos, **saldo em aberto** (soma dos saldos nos jogos finalizados, em SQL pelo scope `Usuario::comSaldoEmAberto()`, ordenável; positivo = deve ao caixa; só com `financeiro.ver`) e **Ações**: o check verde (ativo) vira o X vermelho (inativo) num clique, e volta, sem abrir nada (ação `ativo`, quem pode editar), Editar e Excluir |
| `SegurancaResource` | `/painel/seguranca`, resource simples (modal). Contas do painel: nome, usuário (minúsculo; letras, números, ponto, traço, sublinhado), senha (em branco mantém), ativo (a própria conta não se desativa), grupos e permissões avulsas do painel, último acesso. **Ninguém dá o que não tem** (`App\Support\Permissoes::abrange`): só aparecem os grupos e permissões que a conta logada cobre, e a `SegurancaPolicy` não deixa editar nem excluir conta com mais poder (Admin não mexe no Desenvolvedor) nem excluir a própria |
| `GrupoResource` | Em Configurações (`grupos.*`, só o Desenvolvedor). Abas Todos · Painel · Site. Nome, lado (painel ou site, fixo depois de criar) e a grade de permissões (`Permissoes::opcoes`): no painel uma linha por cadastro (ver, criar, editar, excluir) e depois relatórios, financeiro e configurações; no site, a lista do que se faz na noite. Grava pelo `syncPermissions` (limpa o cache do spatie). Quem entra no grupo se escolhe na conta (Segurança) ou no usuário (Usuários); grupo com gente não se exclui |
| `MesaResource` | As mesas do clube para **aluguéis e reservas**: capacidade, valor de aluguel, ativa, observações; a coluna "Na agenda" conta os eventos, e mesa com evento não se exclui (desative). O torneio não usa este cadastro: as mesas dele ficam na aba Mesas do jogo |
| `AluguelResource` | Tela 23. Totais por status (seguem o filtro de período), abas por status, filtro de período, Marcar pago (grava `pago_em`), Novo aluguel e Editar com o `EventoForm`; excluir apaga o evento (sai do calendário) |
| `TemporadaResource` | Em Configurações |

Cada resource tem policy consultando `recurso.acao`; o Filament esconde menu, botões e ações sem permissão. As policies estendem `App\Policies\PoliticaDoPainel`, que traduz as ações do Filament (`viewAny`/`view` → `ver`, `create` → `criar`, `update` → `editar`, `delete`/`restore` → `excluir`) e nunca permite exclusão definitiva. Restrições extras ficam na policy do model (ex.: `MesaPolicy` não exclui mesa usada em jogo). Booleanos nativos: `Toggle`/`Checkbox` direto.

Convenções dos resources:
- Ícones do menu do set `rh` do design system (`'rh-table'`, `'rh-users'`…), como no AdminShell do protótipo.
- `navigationSort` de 10 em 10 na ordem do menu acima (Jogos 30, Usuários 60, Mesas 70…).
- Dinheiro nas colunas com `Dinheiro::brl()`; nos campos, `numeric` com prefixo `R$`.
- Cadastros pequenos (mesas) são resources simples (`--simple`): uma página, criar e editar em modal.

## Painel de controle

Página `App\Filament\Pages\Dashboard` (tela 16; substitui o Dashboard padrão e o `AccountWidget`): a porta de entrada do painel, com subtítulo do mês e botão **Novo jogo**. Grade de 3 colunas no desktop (destaque e atalhos em 2, ranking e agenda em 1; o resto uma embaixo da outra). Os widgets são Blade com as classes do design system no tema (`rh-destaque`, `rh-ranking`, `rh-atalhos`, `rh-stat`, `rh-datebox`, `rh-li`) e carregam junto com a página (`$isLazy = false`); os lado a lado ficam da mesma altura (`rh-wi-cheio`). Números do `App\Services\Painel\Indicadores` (centavos, pela `Calculadora` e pelos `Saldos`).

- `ProximoJogo` (`jogos.ver`), em destaque: o jogo em andamento ou o próximo agendado ("Próximo torneio · em 5 dias"), data por extenso e horário, Editar (ou Ver) e Abrir no site ("Abrir a noite" com o jogo em andamento).
  - **Onde a noite está** (`ProximoJogo::etapas`): agendado → rebuys → intervalo → entradas fechadas → finalizado no torneio (agendado → em andamento → finalizado no cash game), o atual com o ponto vermelho.
  - Números em 3 colunas. Agendado: confirmados, mesas, buy-in, blinds (ou valor da ficha), prize pool estimado (`Calculadora::simular` com os confirmados; só `financeiro.ver`) e em quais rankings vale ponto. Em andamento: em jogo ("9 de 12"), entradas, nível (pelo relógio, sem gravar; sem estrutura, o digitado na tela ao vivo), mesas e, com `financeiro.ver`, arrecadado e prize pool.
  - Sem jogo: "Nenhum jogo agendado", quantos estão em rascunho (Ver jogos) e Novo jogo.
- `RankingEmDestaque` (`rankings.ver`): dos rankings ativos que valem hoje (`Ranking::valendoHoje`), o que já distribuiu mais pontos (`Indicadores::rankingEmDestaque`). O líder num bloco com o troféu (pontos, vitórias e jogos), do 2º ao 5º numa lista, link para a Classificação e os outros que valem hoje. A conta é a mesma da tela de classificação (`Ranking::classificacao` + `ordenarClassificacao`).
- `CaixaDoMes` (`financeiro.ver`): arrecadação e prize pools do mês (jogos em andamento ou finalizados), aluguéis pagos no mês e **valores pendentes**: o que os jogadores devem nos jogos finalizados + aluguéis reservados ou confirmados cuja data já passou, e quanto o caixa deve. O "ver" abre a lista (jogo, jogador, quanto, link para o fechamento; aluguéis com link para a tela de aluguéis).
- `Atalhos`: os módulos do menu (`App\Services\Painel\Atalhos`), só com o que a conta pode abrir, na ordem do menu. Lê os resources e as páginas do próprio painel (grupo, rótulo, ícone, `canAccess`), então um cadastro novo entra sozinho; cada tela traz uma linha de contexto ("1 agendado · 2 em rascunho", "24 ativos", "1 de 2 ativas", "2 em aberto"…).
- `Proximos` (`eventos.ver`): agenda de hoje em diante (7 itens), com data, tipo, horário, mesas e "rascunho"; cada linha abre o mês no calendário.

Os gráficos dos últimos jogos ficam no rodapé das **Métricas** (Dashboards e relatórios): `ArrecadacaoPorJogo` e `PrizePoolPorJogo` (`financeiro.ver`), `JogadoresPorJogo` e `JogosPorMes` (`jogos.ver`), base `GraficoDeBarras`. Últimos 8 jogos finalizados (rótulo "#18" pelo nome, ou a data) e os últimos 6 meses, sem seguir o filtro da página. Barras no cinza da rampa (`--rh-barra`), só a última em `red-500` (um acento por gráfico), valor em cima ("2,1k") e dica com o nome e o valor. Sem Chart.js.

## Páginas custom

- **Calendário** (`App\Filament\Pages\Calendario`, `/painel/calendario?mes=2026-10`) — grade mensal `rh-cal` em Blade (domingo a sábado, com os dias vizinhos), navegação por mês e Hoje, legenda por tipo. Torneio em vermelho cheio; aluguel e reserva neutros com um ponto âmbar ou azul (o mesmo da legenda); bloqueio listrado. Sem plugin de calendário. Estilos `rh-cal`, `rh-ev`, `rh-dot`, `rh-legend`, `rh-kv` no tema do painel (claro e escuro).
  - Clicar num dia abre **Novo evento** com a data; clicar num aluguel, reserva ou bloqueio abre o editar (com Excluir). O jogo, e qualquer evento para quem só vê, abre o resumo (status, confirmados, buy-in; Ver no painel e Abrir no site).
  - **Novo evento** (tela 18): `App\Filament\Schemas\EventoForm`, o mesmo da tela de aluguéis. Tipo (aluguel de mesa, reserva, bloqueio), cliente (aluguel) ou nome, data, horário inicial e final (vazio = dia todo; pode passar da meia-noite), mesas, responsável, valor e status do aluguel (o valor sugere a soma do aluguel das mesas), observações. Grava pelo `Actions\Agenda\SalvarEvento`.
  - Aviso de mesa ocupada (`Services\Agenda\Conflitos`): mostra se a mesa está livre ou com quem ela já está nesse horário. Só avisa, não impede.
  - Jogo não se cria aqui: o botão **Novo jogo** leva ao cadastro, e o evento do jogo vem do `EventoDoJogo`. Jogo em rascunho aparece tracejado; aluguel cancelado sai da grade.
  - Permissões: calendário com `eventos.*` (o Geral só vê), tela de aluguéis com `alugueis.*`.
- **Métricas** (`App\Filament\Pages\Metricas`, `/painel/metricas`; `relatorios.ver`) — as médias dos jogos **finalizados** no período (o mesmo `Filtro` dos relatórios, padrão Tudo), calculadas no `App\Services\Relatorios\Metricas`:
  - Cartões: jogos finalizados, média de jogadores, média de prize pool, gasto por jogador (arrecadação ÷ jogadores, então cada noite pesa pelo tamanho dela), média de arrecadação, rebuys e add-ons por jogador e meses com jogo. Médias em dinheiro arredondadas para o real.
  - Três comparações, do maior gasto por jogador para o menor, cada grupo com "+X% da média" / "−X% da média" do gasto geral: **por tipo de evento** (o modelo de onde o jogo saiu, `jogos.modelo_id`; sem modelo = "Sem modelo"), **torneio × cash game** (no cash game o prize pool é o que está nas fichas) e **pelo valor do buy-in** (o buy-in padrão do jogo).
  - Mês a mês: gasto por jogador e média de jogadores, um gráfico de barras para cada (nunca dois eixos), o mês mais recente em destaque.
  - No rodapé, os gráficos dos últimos jogos (ver Painel de controle), que não seguem o filtro.
  - `jogos.modelo_id` nasce no **Criar de modelo** e passa adiante no **Duplicar**; jogo antigo ou criado do zero escolhe no select **Tipo de evento** do cadastro. O próprio modelo não tem.
- **Relatórios** (`App\Filament\Pages\Relatorios`, `/painel/relatorios?relatorio=premiacoes`, tela 24; `relatorios.ver`) — as contas ficam no `App\Services\Relatorios\Relatorios`, que devolve um `Relatorio` (colunas, linhas cruas em centavos e cartões de resumo); a tela e os arquivos só formatam.
  - Filtros (`Services\Relatorios\Filtro`): período (temporadas, este mês, mês passado, últimos 30 dias, este ano, tudo ou personalizado; padrão = temporada de hoje, senão o ano), jogo e jogador. O período vale pela data do jogo ou do aluguel.
  - Seis relatórios, escolhidos nos cartões: **Jogos** (todos menos rascunho: status, confirmados, jogadores com buy-in, entradas, mesas, arrecadado), **Entradas** (buy-ins, rebuys, add-ons e opcionais por jogo; com jogador, só as dele), **Prize pools** (arrecadado, taxas, fora da premiação e prize pool), **Premiações** (só finalizados; por jogador, do melhor resultado para o pior, com ITM; com jogador escolhido, uma linha por jogo com a posição), **Aluguéis** (pago, a receber e cancelado) e **Arrecadação** (por mês: arrecadado, prize pools, taxas, fora da premiação, aluguéis pagos e o que ficou fora dos prêmios). Entradas, prize pools e arrecadação contam jogos em andamento e finalizados. Aluguéis e arrecadação não usam os filtros de jogo e jogador (a tela avisa).
  - Exportar CSV e Excel (`relatorios.exportar`, `Services\Relatorios\Exportacao`): o CSV sai no jeito do Excel em português (`;`, "1890,00", BOM UTF-8); o .xlsx usa o OpenSpout (vem com o Filament), com dinheiro como número em R$ e data como data. Arquivo `relatorio-<tipo>-<data>`.
  - **Exportar PDF** sem dompdf: abre em outra aba a página para imprimir (`Http\Controllers\Painel\ImprimirRelatorio`, rota autenticada do painel `filament.painel.relatorios.imprimir`, `relatorios.exportar`), com o mesmo relatório e filtros, em tela clara (fontes Barlow locais), e já chama o diálogo do navegador (`imprimir=1`): o "Salvar como PDF" é do próprio navegador. A4 em pé, ou deitado quando o relatório tem mais de 6 colunas; o cabeçalho da tabela se repete em cada página.
- **Configurações** (`App\Filament\Pages\Configuracoes`, `/painel/configuracoes`, menu "Padrões e premiação"; `configuracoes.editar`, tela 25):
  - **Padrões do jogo** (chave `jogo.padroes` em `configuracoes`, `App\Support\PadroesDoJogo`): horário, lugares por mesa, mesa final, valor e fichas do buy-in, rebuy e add-on padrão e uma taxa padrão em % (ex.: Casa 10%). Valem ao criar jogo ou modelo; os que existem não mudam.
  - **Prêmios** (chave `premios.arredondar`, `App\Support\ArredondarPremios`, ligado por padrão): "Arredondar prêmios para R$ 5". Do 2º para baixo o prêmio desce para o múltiplo de R$ 5 e a diferença vai para o 1º. Vale para regra do clube e manual, ao fechar as entradas; quem já fechou não muda. A tela 12 avisa quando os prêmios gravados foram arredondados.
  - **Premiação pela regra do clube** (`regras_premiacao`): faixas de jogadores (de, até) com os percentuais "50 / 30 / 20". Confere ao salvar: começa em 1 ou 2, sem buraco nem sobreposição, só a última sem limite, percentuais positivos somando 100% e no máximo um prêmio por jogador da faixa. Vale ao fechar as entradas; quem já fechou não muda.
  - Ficaram de fora do protótipo: identidade do clube (nome, logo, cor, cidade, fuso, endereço) e as outras chaves de financeiro e preferências (taxa só sobre buy-ins, confirmar desfazer, acertos automáticos), porque mudariam a marca ou as contas da noite sem regra combinada.
  - Rodar o `RegrasPremiacaoSeeder` de novo volta as três faixas padrão por cima do que foi editado (`updateOrCreate` pela faixa); o `PermissoesSeeder` ressincroniza as permissões dos grupos padrão.

## Deploy do painel

- Subir `vendor/` (Filament + Livewire) e rodar `php artisan filament:assets`.
- O tema custom sai no `npm run build` junto com o site.
