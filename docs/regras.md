# Modelo de dados e regras do jogo

## Convenções

- Tabelas e colunas em português (`usuarios`, `nome`, `apelido`). Tabelas internas do Laravel ficam com o nome padrão (`sessions`, `cache`, `jobs`).
- Sim/não como `boolean` nativo (true/false). Diferente do casamento, que usava `'S'`/`'N'`.
- Dinheiro em `decimal(10,2)` com cast `decimal:2`; contas sempre em centavos via `App\Support\Dinheiro`.
- Status e tipos como Enums PHP em `app/Enums`; o banco guarda a string.

## Modelo de dados (28 tabelas na v1, +2 na v2)

### Acesso: segurança, usuários e permissões

Painel e site têm logins separados, cada um com seu guard:

- **Painel** → guard `painel`, tabela `seguranca`, model `App\Models\Seguranca`. Filament: `->authGuard('painel')`.
- **Site** → guard `web`, tabela `usuarios`, model `App\Models\Usuario`. Entra pelo e-mail e senha; só quem tem `acessa_site` e está ativo (excluído também não entra). 5 tentativas erradas por e-mail+IP bloqueiam por 1 minuto. Esqueceu a senha: o organizador troca no painel (sem recuperação por e-mail na v1).

Permissões dos dois lados com `spatie/laravel-permission` (v6), usando o `guard_name` para separar painel e site. Tabelas renomeadas em `config/permission.php`. Grupo = conjunto de permissões; cada conta pode ter grupos e permissões avulsas.

| Tabela | Colunas principais | Observações |
|---|---|---|
| `seguranca` | nome, login (único), password, ativo, ultimo_acesso, remember_token, timestamps | Contas do painel |
| `usuarios` | nome, apelido, telefone, foto, acessa_site, email, password, pode_ser_dealer, pode_ser_floor, ativo, observacoes, created_at (data de cadastro), updated_at, deleted_at, remember_token | Todas as pessoas. Grupo via `modelo_grupos` (spatie), não é coluna. Jogador fica sem grupo de site e sem `acessa_site`. Jogos, pontos e dinheiro se ligam ao `id` daqui |
| `grupos` | name, guard_name | `roles` do spatie. Painel: Admin, Desenvolvedor, Geral. Site: Admin, Operador. Novos grupos pelo painel |
| `permissoes` | name, guard_name | `permissions` do spatie. Criadas por seeder |
| `grupo_permissoes` | | `role_has_permissions` |
| `modelo_grupos` | | `model_has_roles` |
| `modelo_permissoes` | | `model_has_permissions` (permissões avulsas) |

Quem cuida do painel e também opera a noite tem uma conta em cada tabela.

Módulo de grupos no painel (`GrupoResource`): em cada grupo marcam-se as permissões numa grade de checkboxes; depois usuários (site) ou contas (painel) são atribuídos ao grupo, na própria conta ou no usuário. Grupo com alguém dentro não se exclui.

Ninguém dá o que não tem: na tela de contas do painel só aparecem os grupos e as permissões que a conta logada também tem, e não se edita nem exclui conta com mais permissões que a sua (Admin não troca a senha do Desenvolvedor). A própria conta não se exclui nem se desativa. Rodar o `PermissoesSeeder` de novo volta as permissões dos grupos padrão às do seed.

| Grupo | Guard | Pode (seed inicial, editável) |
|---|---|---|
| Desenvolvedor | painel | Tudo, inclusive segurança, grupos, permissões e configurações técnicas |
| Admin | painel | Tudo de negócio (jogos, modelos, estruturas de blinds, rankings, usuários, mesas, agenda, aluguéis, relatórios, financeiro, contas do painel); não edita grupos/permissões |
| Geral | painel | Ver jogos, estruturas de blinds, agenda, rankings e usuários; cadastrar usuários. Sem financeiro, relatórios, segurança, configurações |
| Admin | web | Tudo no site, inclusive alterar entradas, opcionais e taxas durante o jogo, fases, desfazer, finalizar |
| Operador | web | Só lança: buy-in, rebuy, add-on, opcionais, pagamentos, eliminações; confirma jogadores e faz cadastro rápido. Não altera o torneio |

**Permissões do painel** (`guard_name = painel`), formato `recurso.acao`:
`{jogos,modelos,estruturas,rankings,usuarios,seguranca,grupos,mesas,eventos,alugueis,temporadas}.{ver,criar,editar,excluir}`, `relatorios.ver`, `relatorios.exportar`, `financeiro.ver`, `configuracoes.editar`. Cada resource do Filament tem uma policy que consulta essas permissões.

**Permissões do site** (`guard_name = web`):
- Operador: `entradas.registrar`, `pagamentos.registrar`, `eliminacoes.registrar`, `jogadores.confirmar`, `usuarios.cadastro_rapido`.
- Só Admin: `mesas.organizar`, `jogo.fases`, `relogio.controlar` (v2), `jogo.finalizar`, `acoes.desfazer`, `estrutura.editar` (entradas e opcionais), `taxas.editar`, `financeiro.ver`.

Checadas nas Actions (Gate) e com `@can` no Blade para esconder botões.

### Cadastros gerais

| Tabela | Colunas principais | Observações |
|---|---|---|
| `mesas` | nome, lugares, valor_aluguel, ativa, observacoes | Mesas físicas |
| `temporadas` | nome, inicio, fim | Ex.: "2026/2" |
| `rankings` | nome, periodicidade, inicio, fim, ft_pontua, pontos_mesa_final, participacao_pontua, pontos_participacao, ativo | `periodicidade`: `semanal` / `mensal` / `anual` / `livre`. Vários ativos ao mesmo tempo |
| `ranking_pontuacoes` | ranking_id, posicao, pontos | Só pontuam as posições listadas (só o 1º, top 3, top 5…) |
| `configuracoes` | chave (única), valor (json) | Nome do clube, logo, cor, padrões. Lida com cache |
| `regras_premiacao` | jogadores_min, jogadores_max, percentuais (json) | Até 10: 65/35 · 11–18: 50/30/20 · 19+: 45/27/17/11 |

### O jogo (torneio ou cash game)

| Tabela | Colunas principais | Observações |
|---|---|---|
| `jogos` | e_modelo, nome_modelo, modalidade, temporada_id, nome, data, horario, status, fase, floor_id, metodo_premiacao, lugares_por_mesa, tamanho_mesa_final, estrutura_blinds_id, nivel_atual, blinds_atual, relogio_nivel_id, relogio_segundos, relogio_desde, relogio_iniciado_em, valor_ficha, observacoes, iniciado_em, rebuys_encerrados_em, entradas_fechadas_em, finalizado_em | `e_modelo = true` = torneio modelo (só para copiar; fora do site e do calendário). `modalidade`: `torneio` / `cash_game`. `floor_id` → `usuarios` |
| `jogo_itens` | jogo_id, tipo, nome, valor, fichas, destino, compravel_com (json), obrigatorio, limite_por_jogador, padrao, ativo, ordem | O que o jogador pode comprar. `tipo`: `buyin` / `rebuy` / `addon` / `opcional`. `padrao = true` no buy-in, rebuy e add-on que já vêm no cadastro (não se apagam, só se desativam); variações como "Rebuy duplo" são linhas extras do mesmo tipo. `destino`: `premiacao` / `fora` / `metade` (buy-in, rebuy e add-on sempre `premiacao`). Opcional: `compravel_com` = `[buyin, rebuy, addon]` (junto de cada entrada) ou `[jogador]` (1 por jogador) |
| `jogo_taxas` | jogo_id, nome, calculo, valor, quantidade, ordem | Tiradas da premiação antes de dividir (dealers, casa…). `calculo`: `fixo` / `percentual` / `por_unidade` / `por_jogador` / `por_entrada` |
| `jogo_premiacoes` | jogo_id, posicao, percentual, valor | Só torneio. `valor` gravado ao fechar entradas |
| `jogo_mesas` | jogo_id, mesa_id, dealer_id, lugares, ordem | `dealer_id` → `usuarios` com `pode_ser_dealer` |
| `jogo_ranking` | jogo_id, ranking_id | Rankings em que o jogo vale ponto (torneio ou cash game) |
| `participantes` | jogo_id, usuario_id, jogo_mesa_id, lugar, status, posicao_final, premio, fichas_finais, cash_out | Únicos: (jogo_id, usuario_id) e (jogo_mesa_id, lugar). `status`: `confirmado` / `jogando` / `eliminado` / `saiu` |
| `entradas` | uuid (único), jogo_id, participante_id, jogo_item_id, tipo, valor, fichas, destino, vinculada_a, registrado_por, cancelada_em, cancelada_por, motivo_cancelamento | Cada item comprado. `valor`, `fichas` e `destino` copiados do item no momento; `vinculada_a` liga o opcional à entrada com que foi comprado. Nunca apagada |
| `pagamentos` | uuid (único), jogo_id, participante_id, direcao, valor, forma, observacao, registrado_por, cancelado_em, cancelado_por, motivo_cancelamento | `direcao`: `jogador_pagou` / `jogador_recebeu`. `forma`: `pix` / `dinheiro` / `cartao` / `outro` |
| `eliminacoes` | jogo_id, participante_id, nivel, definitiva, voltou_com, registrada_por, cancelada_em, cancelada_por, motivo_cancelamento | Um jogador pode cair, voltar com rebuy e cair de novo. `voltou_com` → `entradas`: o rebuy com que voltou de uma eliminação provisória (ela fica como histórico) |
| `ranking_pontos` | ranking_id, jogo_id, usuario_id, posicao, pontos, detalhe (json) | Gravado ao finalizar; mudar a regra depois não altera pontos já dados |
| `acertos` | jogo_id, de_participante_id, para_participante_id, valor, pago, pago_em | Sugestão do fechamento. Recebedor nulo = caixa |

### Agenda e aluguéis

| Tabela | Colunas principais | Observações |
|---|---|---|
| `eventos` | tipo, nome, data, hora_inicio, hora_fim, responsavel_id, jogo_id, observacoes | `tipo`: `jogo` / `aluguel` / `reserva` / `bloqueio`. Criar um jogo cria o evento |
| `evento_mesa` | evento_id, mesa_id | Um evento pode ocupar várias mesas |
| `alugueis` | evento_id, cliente, valor, status, pago_em | `status`: `reservado` / `confirmado` / `pago` / `cancelado` |

Regras da agenda (`Actions\Agenda\SalvarEvento`, `Services\Agenda\Conflitos`):
- O evento do jogo vem do cadastro do jogo (`EventoDoJogo`) e não se edita pelo calendário. Aluguel, reserva e bloqueio se criam e editam no calendário (aluguel também na tela de aluguéis).
- Aluguel se chama "Mesa alugada — Cliente" e tem um `alugueis` ligado. Virar reserva ou bloqueio apaga o aluguel; excluir o aluguel apaga o evento.
- Marcar pago grava `pago_em` uma vez (editar de novo não muda a data); sair de pago limpa.
- Horário: sem início = dia todo; sem fim = até o fim do dia; fim antes do início = passa da meia-noite (20:00–01:00).
- Mesa ocupada: outro evento no mesmo dia, com a mesma mesa e horário que se cruza (encostar não conta: 15:00–19:00 e 19:00–23:30 não se cruzam). Só avisa; aluguel cancelado não ocupa mesa e sai do calendário.

## Fases de um torneio

Durante `status = em_andamento`, o torneio passa por três fases (`jogos.fase`), cada uma aberta por um botão do operador:

1. `rebuys` (início até o intervalo): buy-in e rebuy. Eliminação é **provisória**: o jogador pode voltar com rebuy (volta para o mesmo lugar).
2. `intervalo` — botão **Encerrar rebuys**: rebuy fecha, add-on abre (até `limite_por_jogador`). Quem estava eliminado e não voltou vira eliminação definitiva, na ordem em que caiu.
3. `sem_entradas` — botão **Fechar entradas**: add-on fecha, `jogo_premiacoes.valor` é gravado. Daqui em diante só eliminações definitivas, e cada uma dá a posição na hora (quem cai fica em último entre os que restam).

Detalhes (`EncerrarRebuys`, `FecharEntradas`, `Services\Jogo\Posicoes`):
- No intervalo a eliminação já é definitiva, mas a posição só sai ao fechar as entradas: buy-in atrasado ainda vale no intervalo e muda o número de jogadores.
- Fechar entradas só a partir do intervalo (torneio sem add-on: Encerrar rebuys e depois Fechar entradas) e com pelo menos 2 jogadores com buy-in. Grava em `jogo_premiacoes` o percentual e o valor de cada posição pela `Calculadora` (faixa da regra do clube ou percentuais manuais); sem faixa para o número de jogadores, não fecha.
- Posição: N = jogadores com buy-in válido; a k-ésima eliminação definitiva, na ordem em que caíram (0, 1, 2…), fica em N − k. Quando sobra um só, ele é o 1º. `participantes.premio` = valor gravado da posição (vazio fora da premiação).
- As posições são refeitas a cada eliminação e a cada eliminação desfeita (quem caiu depois sobe uma posição).

## Lista da noite (jogadores confirmados)

- Confirmar põe o jogador em `participantes` com status `confirmado` e sem lugar; vale com o jogo agendado ou em andamento (quem chega atrasado). Confirmar de novo não duplica. Jogador inativo não entra.
- Tirar da lista apaga a linha do participante, mas só enquanto ele não tem entrada, pagamento nem eliminação; depois disso ele fica (o caixa já o conhece).
- Cadastro rápido (`usuarios.cadastro_rapido`) cria o jogador só com nome e apelido opcional e já confirma. Se o nome ou o apelido bater com o nome ou o apelido de alguém (sem diferença de maiúsculas), não cria: o aviso manda confirmar pela lista.
- Confirmar e tirar pedem `jogadores.confirmar` (Operador e Admin do site).

## Mesas e início do jogo

- Montar as mesas pede `mesas.organizar` (Admin do site); o Operador só vê.
- Trocar de lugar: clicar no jogador e depois num lugar. Lugar vazio = mover; ocupado = os dois trocam (quem estava vai para o lugar antigo, ou fica sem lugar). Vale com o jogo agendado ou em andamento (quebra de mesa). Eliminado ou quem saiu não senta.
- Sortear lugares (só antes de começar) senta todos os confirmados: as mesas ficam equilibradas na proporção dos lugares e os lugares dentro de cada mesa são aleatórios. Sem lugar suficiente, avisa quantos faltam.
- Lugares por mesa de 2 a 12, sem diminuir abaixo de quem está sentado. Dealer só quem tem "Pode ser dealer". Mesa só sai do jogo vazia. Adicionar ou tirar mesa atualiza a agenda.
- **Iniciar** (`jogo.fases`, Admin do site): só agendado e com pelo menos 2 jogadores sentados. Torneio vai para a fase `rebuys`; cash game fica sem fase. Sentados passam a `jogando`; confirmado sem lugar continua esperando e senta depois.

## Ações no jogador (clicar no assento)

O painel do jogador mostra investido, pago, recebido e saldo, e as ações:

| Ação | Quando | Grava |
|---|---|---|
| Entrada (buy-in, rebuy, add-on e opcionais) | Conforme a fase | `entradas` (o opcional marcado vai junto) |
| Pagamento (parcial ou total) | Sempre, inclusive depois de finalizar (pendências) | `pagamentos` |
| Eliminação | Torneio | `eliminacoes` + `participantes.status/posicao_final` |
| Cash-out | Cash game | `participantes.fichas_finais/cash_out` |
| Trocar de lugar ou mesa | Sempre | `participantes.jogo_mesa_id/lugar` (quebra de mesa) |
| Desfazer última ação | Até finalizar | Marca a última entrada/pagamento/eliminação como cancelada, com motivo |

Regras de cada ação (Actions em `app/Actions/Jogo`):

- **Entrada** (`entradas.registrar`, Operador e Admin; `RegistrarEntrada`): só com o jogo em andamento e item ativo do jogo.
  - Buy-in: um por jogador, qualquer variação (simples, duplo…); para mais fichas, rebuy. Vale até Fechar entradas (atrasado entra no intervalo).
  - Rebuy só na fase de rebuys; add-on só no intervalo. Os dois pedem buy-in.
  - O limite por jogador conta as entradas válidas da mesma linha (ex.: add-on 1); vazio = sem limite.
  - Opcionais: os marcados e os obrigatórios vão junto da entrada (`vinculada_a`). O "1 por jogador" entra uma vez só e também pode ser comprado sozinho; obrigatório já comprado não repete; marcado já comprado é erro.
  - Eliminado só volta com rebuy (fase de rebuys): a eliminação provisória ganha `voltou_com` e o jogador volta a jogar, no mesmo lugar.
  - Cash game: buy-in e recompra (rebuy) a qualquer hora em andamento; sem add-on.
- **Pagamento** (`pagamentos.registrar`; `RegistrarPagamento`): valor maior que zero, jogador pagou ou recebeu, forma (pix, dinheiro, cartão, outro) e observação opcional. Com o jogo agendado (pagou antes), em andamento ou finalizado (pendências). Pode passar do que deve: o saldo fica negativo (o caixa devolve).
- **Eliminação** (`eliminacoes.registrar`; `RegistrarEliminacao`): só torneio, jogador jogando e com buy-in, e nunca o último em jogo (quem está sentado sem buy-in não conta; o último é o campeão). Provisória na fase de rebuys; definitiva depois. Guarda o nível atual.
- **Cash-out** (`eliminacoes.registrar`; `RegistrarCashOut`): só cash game, jogador com buy-in e valor da ficha no jogo. O jogador sai (`saiu`); registrar de novo corrige o valor.
- **Lugar de quem saiu**: eliminado e quem fez cash-out continuam no lugar (a mesa mostra apagado) até alguém sentar ali, e aí ficam sem lugar em vez de trocar. Diminuir a mesa tira do lugar quem já saiu; tirar a mesa ainda pede que ninguém esteja jogando nela. Quem chega atrasado e senta com o jogo em andamento passa a jogando.
- **Desfazer** (`acoes.desfazer`, só Admin; `DesfazerAcao`): com motivo, só em andamento, sempre a última ação do jogador (a mais recente entre entrada, eliminação e pagamento; opcional junto de outra entrada vai com ela; no mesmo segundo vale a ordem entrada → eliminação → pagamento).
  - Entrada leva junto os opcionais comprados com ela e só se desfaz até Fechar entradas. Rebuy que trouxe o jogador de volta devolve a eliminação (definitiva, se os rebuys já acabaram).
  - Eliminação desfeita: o jogador volta a jogar e as posições são refeitas.
  - Cash-out não se desfaz: registrar de novo corrige.

## Cash game

Sem eliminação, posição ou premiação. Ações: entrada (buy-in/recompra), pagamento, cash-out.

- Cash-out = fichas finais × `jogos.valor_ficha`.
- Resultado = cash-out − investido.
- Entradas, opcionais e taxas iguais ao torneio.
- Conferência: soma dos cash-outs = valor da premiação (o que está nas fichas). Se não bater, mostra a diferença antes de finalizar.

## Dinheiro do jogo: entradas, opcionais e taxas

Tudo definido no cadastro do jogo (ou no modelo), em três blocos. Durante o jogo, só o Admin do site (`estrutura.editar`, `taxas.editar`) muda algo, até **Fechar entradas**. O que já foi lançado mantém o valor, as fichas e o destino da hora.

**1. Entradas** — buy-in, rebuy e add-on já vêm no cadastro, um de cada, com valor (R$) e fichas. O **+** de cada um adiciona uma variação do mesmo tipo (buy-in duplo, rebuy duplo…), com nome, valor e fichas próprios. Cada linha tem limite por jogador opcional (ex.: buy-in 1, add-on 1, rebuy sem limite). Torneio sem rebuy ou sem add-on: desativa a linha padrão. Entradas vão sempre 100% para a premiação.

**2. Opcionais** — o que mais o jogador pode comprar: opcional de fichas, churrasco… Cada um tem nome, valor, fichas (pode ser 0), se é comprado junto de cada buy-in/rebuy/add-on ou 1 por jogador, se é obrigatório, e o destino:

| `destino` | Vai para a premiação |
|---|---|
| `premiacao` | 100% |
| `metade` | 50% (50/50) |
| `fora` | 0% |

Ao lançar uma entrada, os opcionais compráveis com ela aparecem como checkbox.

**3. Taxas** — saem da premiação **antes** de dividir:

| `calculo` | Como funciona | Exemplo |
|---|---|---|
| `fixo` | Valor único | Casa: R$ 190 |
| `percentual` | % sobre a premiação bruta | 10% |
| `por_unidade` | valor × quantidade | Dealers: 2 × R$ 215 |
| `por_jogador` | valor × jogadores | R$ 10 por jogador |
| `por_entrada` | valor × entradas (buy-in, rebuy, add-on) | R$ 5 por entrada |

O cadastro mostra ao lado uma simulação (arrecadação, premiação bruta, taxas, prize pool, fora da premiação) para N jogadores e N rebuys.

### Exemplo: torneio de sábado

Entradas: 1 buy-in, rebuys ilimitados, 1 add-on. Opcionais: "Opcional" R$ 10 · 20k junto de cada entrada, destino `metade`; "Churrasco" R$ 30 · 20k, 1 por jogador, destino `fora`. Taxas: casa R$ 190 (fixo), dealers R$ 430.

| Linha | Valor |
|---|---|
| Arrecadação total | R$ 4.290 |
| Entradas (100%) | R$ 3.210 |
| Opcional R$ 600 (50%) | + R$ 300 |
| Churrasco R$ 480 (0%) | — |
| **Premiação bruta** | **R$ 3.510** |
| Taxas (casa + dealers) | −R$ 620 |
| **Prize pool** | **R$ 2.890** |
| Fora da premiação | R$ 300 do opcional + R$ 480 do churrasco |

## Rankings

Página própria no painel (`RankingResource`). Vários ao mesmo tempo (anual, mensal, semanal); o jogo escolhe em quais vale (`jogo_ranking`, select múltiplo). Torneio e cash game podem valer.

Cada ranking configura, sem nada fixo no código:
- posições que pontuam e quanto (`ranking_pontuacoes`; só pontua quem está na lista);
- `ft_pontua` + `pontos_mesa_final` (mesa final = `posicao_final <= jogos.tamanho_mesa_final`, padrão 9);
- `participacao_pontua` + `pontos_participacao`.

Cash game: posição para o ranking = ordem do resultado (maior lucro = 1º). Pontos gravados em `ranking_pontos` ao finalizar (`Services\Ranking\Pontuacao`); classificação = soma por usuário no período do ranking; empate desfeito por mais vitórias, melhor posição e mais jogos.

Detalhes da `Pontuacao`: vale nos rankings **ativos** ligados ao jogo; entra quem tem buy-in, com uma linha por jogador em `ranking_pontos` (mesmo com 0 ponto, para contar participação). `detalhe` = `{posicao, mesa_final, participacao}`; `pontos` = a soma. Finalizar de novo (não acontece pelo site) refaz as linhas do jogo.

## Torneios modelo

`jogos.e_modelo = true` guarda um modelo com entradas, opcionais, taxas, premiação, rankings e mesas. Modelos não aparecem no site nem no calendário. `Actions\Jogo\CriarDeModelo` copia tudo e recebe só nome, data e horário. No `JogoResource`, a ação "Salvar como modelo" faz o caminho inverso.

## Cálculos

- **Arrecadação** = soma de `entradas.valor` não canceladas.
- **Premiação bruta** = soma de `entradas.valor` não canceladas × fração do destino (`premiacao` 1, `metade` 0,5, `fora` 0).
- **Taxas** = soma de `jogo_taxas` calculadas (percentual sobre a premiação bruta).
- **Prize pool** = premiação bruta − taxas.
- **Fora da premiação** = arrecadação − premiação bruta.
- **Prêmio da posição** = prize pool × percentual. Com "Arredondar prêmios para R$ 5" (Configurações, ligado por padrão), do 2º para baixo o prêmio desce para o múltiplo de R$ 5; a sobra vai para o 1º.
- **Saldo do jogador** = investido − prêmio − pago + recebido. Positivo: deve ao caixa. Negativo: o caixa deve a ele. No cash game o prêmio é o cash-out. `Services\Jogo\Saldos` soma em centavos a partir das entradas e pagamentos válidos; sem pagamentos, a soma dos saldos é o que fica com a casa (taxas + fora da premiação).

Referência (Poker Night #18): 22 entradas, premiação bruta R$ 2.100; taxa "casa" 10% = R$ 210; prize pool R$ 1.890 → 950 / 565 / 375 (arredondado para R$ 5; sem arredondar, 945 / 567 / 378).

Detalhes da `Calculadora` (tudo em centavos inteiros):
- A fração do destino vale sobre a soma de cada destino, não lançamento a lançamento.
- **Jogadores** (taxa por jogador e regra de premiação) = quem tem pelo menos um buy-in válido. **Entradas** (taxa por entrada) = buy-ins, rebuys e add-ons; opcionais não contam.
- Taxa percentual arredonda meio centavo para cima (7,5% de R$ 606 = R$ 45,45). Taxa por unidade usa quantidade mínima 1.
- Prize pool não fica negativo: se as taxas passarem da premiação bruta, ele é zero.
- Prêmios: cada posição leva prize pool × percentual, para baixo; a sobra (centavos) vai para o 1º, quando os percentuais somam 100%. Arredondando para R$ 5 (`App\Support\ArredondarPremios`, chave `premios.arredondar`), do 2º para baixo desce para o múltiplo de R$ 5 e o 1º fecha a conta: ele só sai redondo se o prize pool for múltiplo de R$ 5. Prêmio menor que R$ 5 fica como está (não vira zero); premiação manual que não soma 100% arredonda o 1º também. Vale ao fechar as entradas, quando os prêmios são gravados. Premiação pela regra usa a faixa do número de jogadores; manual usa os percentuais do jogo. Cash game não tem prêmios.
- **Simulação** (prize pool estimado): N jogadores com o buy-in padrão, N rebuys e N add-ons padrão, mais os opcionais obrigatórios (por jogador ou junto de cada entrada).

## Fechamento e acertos

Com pagamentos registrados durante a noite, quase tudo se acerta pelo caixa. O fechamento mostra o saldo de cada um e sugere quem paga quem para o que sobrar (`Services\Jogo\Acertos`):

1. Credores = saldos negativos (o caixa deve). Devedores = saldos positivos.
2. Primeiro, devedores (ou combinações pequenas) que fecham exatamente o saldo de um credor.
3. Depois, guloso: maior devedor paga o maior credor aberto até zerar um dos dois.
4. Se o caixa tiver dinheiro, ele entra como credor/devedor intermediário (recebedor nulo).

Detalhes (`Services\Jogo\Acertos`, em centavos):
- O caixa (a casa) entra como mais um, com saldo = −(soma dos saldos dos jogadores): ele recebe as taxas e o que ficou fora da premiação, menos o que já tem em mãos dos pagamentos da noite. Com dinheiro sobrando, é ele quem paga. Em `acertos`, participante nulo = o caixa (de um lado ou do outro).
- "Combinações pequenas" = um, dois ou três devedores somando exatamente o saldo de um credor (do maior credor para o menor). A cada transferência a busca recomeça; sem combinação exata, o maior devedor paga o maior credor.
- Cada um termina com o próprio saldo fechado (a soma do que paga menos o que recebe é o saldo dele).

**Resultado** (`Actions\Jogo\DefinirResultado`, `jogo.fases`): com as entradas fechadas, o Admin define a posição de quem ainda está em jogo (acordo na mesa final, horário). Quem está em jogo ocupa do 1º ao Nº, sem repetir; cada um vira uma eliminação definitiva, do último colocado para o 2º, e o 1º fica. Assim a posição continua saindo só das eliminações. Cash game não tem posições.

**Finalizar** (`Actions\Jogo\FinalizarJogo`, `jogo.finalizar`): só em andamento. Torneio: entradas fechadas e posição de todos que fizeram buy-in. Cash game: cash-out de todos que fizeram buy-in. Grava os acertos sugeridos e os pontos dos rankings.

Premiação, resultado e fechamento abrem no site com as entradas fechadas (no cash game, a qualquer hora em andamento) e continuam abertos depois de finalizar; o **Finalizar** fica no fechamento.

**Acerto feito** (`Actions\Jogo\MarcarAcerto`, `pagamentos.registrar`, só depois de finalizar): vira pagamentos para os saldos fecharem. Quem paga ganha um "jogador pagou" e quem recebe um "jogador recebeu", na forma escolhida, com a observação "Acerto: pagou a …" / "recebeu de …"; o caixa não tem linha (o dinheiro dele muda só quando ele é uma das pontas). Marcar de novo não vale.

O jogo pode ser finalizado com saldos em aberto: viram pendências do jogador, aparecem em "Valores pendentes" no painel e são pagas depois pela ação de pagamento.

## Travas

- Buy-in: um por jogador.
- Rebuy só na fase `rebuys`; add-on só no `intervalo`, até o limite.
- Rebuy desfaz a eliminação provisória.
- `uuid` repetido em `entradas`/`pagamentos` é ignorado (toque duplo).
- Todas as ações rodam em transação com `lockForUpdate()` na linha do jogo.
- Finalizado = somente leitura, exceto pagamento de pendências.

## Ciclo de vida do jogo

1. `rascunho` — configurando no painel.
2. `agendado` — botão **Agendar** no painel (exige data, um buy-in ativo com valor e, se a premiação for manual, percentuais somando 100%). Aparece no site e no calendário; confirmar jogadores e montar mesas.
3. `em_andamento` — botão **Iniciar jogo**. Torneio passa pelas fases acima.
4. `finalizado` — botão **Finalizar**; grava resultado, pontos e acertos.

`cancelado` só a partir de `rascunho`/`agendado`.

## Onde fica o código

| Classe | Faz |
|---|---|
| `Services\Jogo\Calculadora` | Arrecadação, premiação bruta, taxas, prize pool, prêmios |
| `Services\Jogo\Saldos` | Investido, pago, recebido, saldo por jogador |
| `Services\Jogo\Posicoes` | Posição e prêmio de quem caiu, com as entradas fechadas |
| `Services\Jogo\Acertos` | Sugestão de quem paga quem |
| `Services\Ranking\Pontuacao` | Pontos por ranking e classificação |
| `Actions\Jogo\RegistrarEntrada`, `RegistrarPagamento`, `RegistrarEliminacao`, `RegistrarCashOut`, `TrocarLugar` | Ações no jogador |
| `Actions\Jogo\DesfazerAcao` | Cancela a última ação com motivo |
| `Actions\Jogo\EncerrarRebuys`, `FecharEntradas`, `FinalizarJogo` | Fases e status |
| `Services\Jogo\Relogio`, `Actions\Jogo\ControlarRelogio` | v2: relógio do torneio com estrutura de blinds |
| `Actions\Jogo\CriarDeModelo` | Novo jogo a partir de um modelo |

## Blinds e relógio (v2)

Na v1 (e na v2, para jogo sem estrutura) o Admin do site (`jogo.fases`) digita nível e blinds na faixa da tela ao vivo (`Actions\Jogo\AtualizarNivel`: nível de 1 a 99 ou vazio, blinds até 40 caracteres, "100/200" vira "100 / 200") e encerra rebuys e fecha entradas nos botões da tela. Na v2:

| Tabela | Colunas principais | Observações |
|---|---|---|
| `estruturas_blinds` | nome, descricao, ativo | Estruturas reutilizáveis ("Turbo 15 min", "Deepstack 20 min") |
| `estrutura_niveis` | estrutura_id, ordem, small_blind, big_blind, ante, duracao_minutos, e_intervalo, nome_intervalo | `ordem` começa em 1. Ante 0 = sem ante. Intervalo é um nível especial: sem blinds, com nome ("Intervalo", "Jantar") |

**Estruturas (etapa 1, feita):**

- Cadastro no painel (`EstruturaBlindsResource`, `estruturas.*`): nome, descrição, ativo e os níveis em ordem (small, big ≥ small, ante, minutos de 1 a 240; ou intervalo com nome e minutos), com ao menos um nível de blinds.
- O número do nível não conta intervalo: no Turbo, "Nível 6" é o 100 / 200, depois vem o Intervalo e o "Nível 7". "Começa em" é o tempo de relógio desde o nível 1 (`EstruturaBlinds::linhas()`).
- O torneio escolhe a estrutura na aba **Blinds** do cadastro (`jogos.estrutura_blinds_id`), só em rascunho e agendado, como entradas e mesas. Sem estrutura, nível e blinds continuam digitados na noite. Cash game não tem estrutura (o `Jogo` limpa ao salvar).
- Duplicar, Salvar como modelo e Criar a partir do modelo levam a estrutura. A conferência (tela 04) mostra o nome e os níveis.
- Estrutura usada num jogo ou modelo não se exclui: desative. `Duplicar` (na lista e no editar) copia com os níveis, para montar uma variação; o nome vira "X (cópia)", "X (cópia 2)"…
- **Gerar próximos níveis** (v3, `App\Support\GeradorDeNiveis`): continua a estrutura a partir do último nível de blinds (intervalo não conta). O padrão sai do big blind dos últimos níveis: com 3 ou mais, a mesma soma ou a mesma razão que se repete (dobrar quando a razão é 2); sem repetição, a razão do último salto; com 2, dobrar se dobrou, senão somar a diferença; com 1, somar o próprio big. Os minutos vêm do último nível de blinds e os do intervalo, do último intervalo. Quem gera escolhe quantos, os minutos, o padrão (dobrar, multiplicar por X ou somar X ao big) e um intervalo a cada N níveis (não depois do último).
  - Valores de ficha: múltiplo de 1, 5, 25, 100, 500 ou 1.000 pelo tamanho (237 → 225; 1.640 → 1.600). Com o small na metade do big, o small arredonda no passo do big e o big é o dobro dele (alvo 13.500 → 7.000 / 14.000); com outra proporção, cada um arredonda sozinho. O ante segue a proporção do último nível (ante do big continua igual ao big). O big sempre sobe: se o arredondamento repetir o valor, sobe um passo.
- Seed (`EstruturasBlindsSeeder`, roda em todo ambiente e só cria a que falta): Turbo 15 min (16 níveis + intervalo depois do 6, 4h15; o nível 6 é o 100 / 200 da Poker Night #18, que usa ela) e Deepstack 20 min (14 níveis + intervalo, ante do big blind a partir do 7, 5h).

**Relógio (etapa 2, a regra; feita):** torneio com estrutura tem relógio (`Jogo::temRelogio()`); cash game e torneio sem estrutura continuam com o nível digitado (`AtualizarNivel` recusa quando há estrutura: "mude o nível pelo relógio").

| Coluna em `jogos` | O que guarda |
|---|---|
| `relogio_nivel_id` | Nível da estrutura em que o relógio está (some se o nível for apagado da estrutura) |
| `relogio_segundos` | Segundos que faltavam no nível quando o relógio foi parado ou posto a correr |
| `relogio_desde` | Desde quando o relógio corre; nulo = pausado |
| `relogio_iniciado_em` | Primeira vez que correu (antes disso o botão é INICIAR) |

- **Sem cron nem WebSocket** (`Services\Jogo\Relogio::estado`): o tempo que falta é `relogio_segundos` − o que passou desde `relogio_desde`; quando acaba, o relógio anda para o próximo nível com o tempo cheio, quantos níveis forem preciso (a tela pode ter ficado fechada). No fim do último nível para em 00:00 ("a estrutura acabou").
- `nivel_atual` e `blinds_atual` seguem o relógio: número do nível de blinds (no intervalo, o último antes dele) e os blinds com ante ("300 / 600 · ante 600"; no intervalo, o nome dele). `Relogio::sincronizar` grava quando o nível muda: a tela ao vivo chama a cada atualização (5 s), o dashboard do site também, e a eliminação e o resultado chamam `avancar` com o jogo travado, para guardar o nível certo.
- **Iniciar o jogo** põe o relógio no nível 1, com o tempo cheio e parado, até alguém apertar INICIAR.
- **Comandos** (`Actions\Jogo\ControlarRelogio`, permissão do site `relogio.controlar`, só o Admin no seed): continuar (o INICIAR da primeira vez), pausar, próximo e anterior (o nível começa com o tempo cheio e o relógio continua correndo ou parado), ± 1 a 60 minutos no nível atual (fica entre 0 e 4 h; tirar mais do que falta passa para o próximo nível). Recusa: "O relógio já está correndo/pausado", "Já está no primeiro/último nível", "A estrutura acabou".
- **Estrutura editada durante o jogo:** o nível atual mantém o tempo que falta; o resto vale do próximo nível em diante. Se o nível atual for apagado da estrutura (ou o jogo começou sem relógio), o relógio parte do nível de blinds com o número de `nivel_atual`, parado e com o tempo cheio.
- `EstadoDoRelogio`: nível, número, tempo ("14:32", "09:05", "1:05:00"), correndo, próximo nível, segundos até o próximo intervalo e quando o nível termina. O tempo mostrado arredonda para cima; `restanteExato()` traz a fração do segundo, para a contagem no navegador virar no instante certo.
- A Poker Night #18 (seed) fica com o relógio parado no nível 6 com 14:32, como a faixa do protótipo.

**Relógio que anda sem internet (v3, etapa 2, feita):**

- A tela manda ao navegador o pacote `EstadoDoRelogio::paraNavegador()` no atributo `data-relogio`: o nível de agora, os segundos que faltam (com a fração), se está correndo e se já começou, a hora do servidor e, para **cada nível** da estrutura, o que mostrar nele: título ("Nível 6", "Intervalo"), rótulo e blinds grandes (no intervalo, os da volta: "Na volta · nível 7"), ante ("Ante 600"), o que vem depois ("Próximo: nível 7 · 150 / 300", "Depois: Intervalo · 15 min", "Último nível da estrutura") e, até o próximo intervalo, os segundos dos níveis do meio e quantos níveis faltam contando o próprio.
- O motor no navegador (`resources/js/site/relogio-torneio.js`, Alpine `relogioTorneio`, um por tela) conta sozinho e passa para o próximo nível quando o tempo acaba, também com a aba em segundo plano e **sem internet**; no fim do último nível para em 00:00 com "Fim da estrutura", como o servidor. Cada poll troca o atributo e o motor acerta a conta pelo servidor (pausa, PRÓXIMO, ANTERIOR e ± minutos valem quando a rede volta).
- Mais de 15 s sem atualização: a TV mostra "Sem conexão · o relógio segue contando" (o pacote traz a hora do servidor, então até com o relógio pausado cada poll conta como notícia).
- O motor avisa a troca de nível (evento `relogio-trocou`) uma vez só por nível: um atraso da rede que traga de volta o nível anterior por um instante não repete o aviso; voltar de verdade (ANTERIOR, com mais de 3 s no nível) libera o aviso dos seguintes. A TV toca o som; a tela ao vivo, quando foi o navegador que passou de nível, pede ao servidor o resto da tela (nível, blinds, aviso de intervalo) sem esperar o próximo poll.

**Relógio na tela ao vivo (etapa 3, feita):**

- A faixa mostra o nível e os blinds do relógio e a célula **Relógio** com o tempo contando no navegador (v3: pelo motor, que passa de nível sozinho; a tela então pede ao servidor o nível e os blinds novos). Pausado fica em âmbar ("Relógio · pausado"; antes do primeiro INICIAR, "Não iniciado"), o último minuto em vermelho.
- No intervalo, a célula do relógio leva o nome dele ("Intervalo") e a do nível mostra o que vem depois ("A seguir · nível 7", "150 / 300"); a mesa mostra "Intervalo".
- Quem tem `relogio.controlar` pausa e inicia no botão ao lado do tempo e, clicando no tempo, abre o modal **Relógio**: o tempo grande, o nível e o que vem depois ("Depois: Intervalo · 15 min", "intervalo em 45 min"), ANTERIOR, INICIAR/CONTINUAR/PAUSAR, PRÓXIMO e −5, −1, +1 e +5 min. Recusa aparece no modal. Os outros só veem o relógio.
- **Sugestão de encerrar rebuys:** quando o relógio está num intervalo e o torneio ainda está nos rebuys, quem muda a fase (`jogo.fases`) vê acima da mesa "Intervalo: hora de encerrar os rebuys e abrir o add-on." com ENCERRAR REBUYS (abre o mesmo modal da fase). Só sugere: nada encerra sozinho, e a sugestão some quando o intervalo acaba.
- Celular: a barra de baixo ganha o relógio na frente (nível e tempo; Admin abre o modal por ela) e fica com quatro células (relógio, blinds sem o ante, entradas e prize pool).

**Link do relógio (v3, etapa 3, feita; no lugar da tela da TV da v2):** cada jogo tem um código (`jogos.relogio_codigo`, 8 letras e números sem os que confundem, como `k7m2q9xp`), criado junto com o jogo; cópia e modelo ganham um novo. O link `/relogio/{codigo}` (`RelogioPublicoController`) abre **sem login** e só para ver, na TV, no tablet, em outro celular ou no notebook.

- **Mostra só o relógio:** o nome do jogo, o nível, o tempo, os blinds e o ante (no intervalo, os da volta), o que vem depois e o intervalo (em tempo e em níveis: "Intervalo em 2 níveis · 29:55"; no intervalo, "Agora"; sem intervalo pela frente, avisa). Nada de dinheiro, jogadores, entradas ou stack médio.
- **Antes de começar:** data, horário, nome e "O relógio começa quando o torneio for iniciado · nível 1: 10 / 20 · 15 min". **Depois de finalizar:** "Fim de jogo" (sem prêmios). Cancelado: "Jogo cancelado". Rascunho, modelo e link trocado: 404 com "Este link não vale mais".
- Torneio sem estrutura (e cash game): o nível e os blinds digitados na tela ao vivo.
- **Atualiza sozinho:** a página carrega uma vez; o motor busca `/relogio/{codigo}/estado` a cada 2 s, uma rota **sem sessão, cookie nem CSRF** (a tela pode ficar horas aberta, até sem internet, sem "página expirou"). O JSON traz o status, a `chave` (muda quando o jogo começa, acaba ou muda o nível digitado: a página recarrega) e o pacote do relógio. Sem internet, segue contando (etapa 2).
- **Onde pegar o link:** botão **TV** da tela ao vivo (modal "Relógio nas outras telas": o link, COPIAR LINK, ABRIR e, para quem tem `relogio.controlar`, GERAR OUTRO LINK, que troca o código pelo `Actions\Jogo\TrocarLinkDoRelogio` e faz o antigo parar de abrir) e a **Conferência** (tela 04), para deixar a TV pronta antes de começar. `/jogos/{jogo}/tv` (endereço antigo) leva para o link.
- O copiar usa `$copiar` (`resources/js/site/copiar.js`): área de transferência em https e localhost, e o jeito antigo no http da rede local.
- **Som na troca de nível** (`resources/js/site/tv.js`, Web Audio, sem arquivo): três notas quando o motor do relógio passa para um nível novo, pelo tempo ou por PRÓXIMO em outro aparelho (uma vez só por nível). O relógio, os blinds, o que vem depois e o "Intervalo em" (tempo e níveis, ex.: "Intervalo em 2 níveis · 28:30") vêm do motor (`<x-site.poker.painel-relogio>`), então seguem andando sem internet. Desligado por padrão; ligado fica guardado no aparelho. O navegador só deixa tocar depois de um toque na página: com o som ligado e sem toque, a tela pede "Toque na tela para ligar o som".
- **Tela cheia** pelo botão (ou F11 da TV); os botões (no celular, só os ícones) e o cursor somem depois de 3 s sem mexer. A tela fica acesa (Screen Wake Lock) onde o navegador deixa (https ou localhost); fora disso, vale a configuração da TV.

Com a etapa 4, a v2 (estruturas de blinds e relógio) está completa.

## Pontos em aberto

- Operador registra eliminação? (assumido: sim)
- Valores de pontuação dos rankings: definir depois (estrutura pronta).
