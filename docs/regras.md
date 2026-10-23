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

Módulo de grupos no painel (`GrupoResource`): em cada grupo marcam-se as permissões numa grade de checkboxes; depois usuários (site) ou contas (painel) são atribuídos ao grupo.

| Grupo | Guard | Pode (seed inicial, editável) |
|---|---|---|
| Desenvolvedor | painel | Tudo, inclusive segurança, grupos, permissões e configurações técnicas |
| Admin | painel | Tudo de negócio (jogos, modelos, rankings, usuários, mesas, agenda, aluguéis, relatórios, financeiro, contas do painel); não edita grupos/permissões |
| Geral | painel | Ver jogos, agenda, rankings e usuários; cadastrar usuários. Sem financeiro, relatórios, segurança, configurações |
| Admin | web | Tudo no site, inclusive alterar entradas, opcionais e taxas durante o jogo, fases, desfazer, finalizar |
| Operador | web | Só lança: buy-in, rebuy, add-on, opcionais, pagamentos, eliminações; confirma jogadores e faz cadastro rápido. Não altera o torneio |

**Permissões do painel** (`guard_name = painel`), formato `recurso.acao`:
`{jogos,modelos,rankings,usuarios,seguranca,grupos,mesas,eventos,alugueis,temporadas}.{ver,criar,editar,excluir}`, `relatorios.ver`, `relatorios.exportar`, `financeiro.ver`, `configuracoes.editar`. Cada resource do Filament tem uma policy que consulta essas permissões.

**Permissões do site** (`guard_name = web`):
- Operador: `entradas.registrar`, `pagamentos.registrar`, `eliminacoes.registrar`, `jogadores.confirmar`, `usuarios.cadastro_rapido`.
- Só Admin: `mesas.organizar`, `jogo.fases`, `jogo.finalizar`, `acoes.desfazer`, `estrutura.editar` (entradas e opcionais), `taxas.editar`, `financeiro.ver`.

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
| `jogos` | e_modelo, nome_modelo, modalidade, temporada_id, nome, data, horario, status, fase, floor_id, metodo_premiacao, lugares_por_mesa, tamanho_mesa_final, nivel_atual, blinds_atual, valor_ficha, observacoes, iniciado_em, rebuys_encerrados_em, entradas_fechadas_em, finalizado_em | `e_modelo = true` = torneio modelo (só para copiar; fora do site e do calendário). `modalidade`: `torneio` / `cash_game`. `floor_id` → `usuarios` |
| `jogo_itens` | jogo_id, tipo, nome, valor, fichas, destino, compravel_com (json), obrigatorio, limite_por_jogador, padrao, ativo, ordem | O que o jogador pode comprar. `tipo`: `buyin` / `rebuy` / `addon` / `opcional`. `padrao = true` no buy-in, rebuy e add-on que já vêm no cadastro (não se apagam, só se desativam); variações como "Rebuy duplo" são linhas extras do mesmo tipo. `destino`: `premiacao` / `fora` / `metade` (buy-in, rebuy e add-on sempre `premiacao`). Opcional: `compravel_com` = `[buyin, rebuy, addon]` (junto de cada entrada) ou `[jogador]` (1 por jogador) |
| `jogo_taxas` | jogo_id, nome, calculo, valor, quantidade, ordem | Tiradas da premiação antes de dividir (dealers, casa…). `calculo`: `fixo` / `percentual` / `por_unidade` / `por_jogador` / `por_entrada` |
| `jogo_premiacoes` | jogo_id, posicao, percentual, valor | Só torneio. `valor` gravado ao fechar entradas |
| `jogo_mesas` | jogo_id, mesa_id, dealer_id, lugares, ordem | `dealer_id` → `usuarios` com `pode_ser_dealer` |
| `jogo_ranking` | jogo_id, ranking_id | Rankings em que o jogo vale ponto (torneio ou cash game) |
| `participantes` | jogo_id, usuario_id, jogo_mesa_id, lugar, status, posicao_final, premio, fichas_finais, cash_out | Únicos: (jogo_id, usuario_id) e (jogo_mesa_id, lugar). `status`: `confirmado` / `jogando` / `eliminado` / `saiu` |
| `entradas` | uuid (único), jogo_id, participante_id, jogo_item_id, tipo, valor, fichas, destino, vinculada_a, registrado_por, cancelada_em, cancelada_por, motivo_cancelamento | Cada item comprado. `valor`, `fichas` e `destino` copiados do item no momento; `vinculada_a` liga o opcional à entrada com que foi comprado. Nunca apagada |
| `pagamentos` | uuid (único), jogo_id, participante_id, direcao, valor, forma, observacao, registrado_por, cancelado_em, cancelado_por, motivo_cancelamento | `direcao`: `jogador_pagou` / `jogador_recebeu`. `forma`: `pix` / `dinheiro` / `cartao` / `outro` |
| `eliminacoes` | jogo_id, participante_id, nivel, definitiva, registrada_por, cancelada_em, cancelada_por, motivo_cancelamento | Um jogador pode cair, voltar com rebuy e cair de novo |
| `ranking_pontos` | ranking_id, jogo_id, usuario_id, posicao, pontos, detalhe (json) | Gravado ao finalizar; mudar a regra depois não altera pontos já dados |
| `acertos` | jogo_id, de_participante_id, para_participante_id, valor, pago, pago_em | Sugestão do fechamento. Recebedor nulo = caixa |

### Agenda e aluguéis

| Tabela | Colunas principais | Observações |
|---|---|---|
| `eventos` | tipo, nome, data, hora_inicio, hora_fim, responsavel_id, jogo_id, observacoes | `tipo`: `jogo` / `aluguel` / `reserva` / `bloqueio`. Criar um jogo cria o evento |
| `evento_mesa` | evento_id, mesa_id | Um evento pode ocupar várias mesas |
| `alugueis` | evento_id, cliente, valor, status, pago_em | `status`: `reservado` / `confirmado` / `pago` / `cancelado` |

## Fases de um torneio

Durante `status = em_andamento`, o torneio passa por três fases (`jogos.fase`), cada uma aberta por um botão do operador:

1. `rebuys` (início até o intervalo): buy-in e rebuy. Eliminação é **provisória**: o jogador pode voltar com rebuy (volta para o mesmo lugar).
2. `intervalo` — botão **Encerrar rebuys**: rebuy fecha, add-on abre (até `limite_por_jogador`). Quem estava eliminado e não voltou vira eliminação definitiva, na ordem em que caiu.
3. `sem_entradas` — botão **Fechar entradas**: add-on fecha, `jogo_premiacoes.valor` é gravado. Daqui em diante só eliminações definitivas, e cada uma dá a posição na hora (quem cai fica em último entre os que restam).

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

Cash game: posição para o ranking = ordem do resultado (maior lucro = 1º). Pontos gravados em `ranking_pontos` ao finalizar (`Services\Ranking\Pontuacao`); classificação = soma por usuário no período do ranking.

## Torneios modelo

`jogos.e_modelo = true` guarda um modelo com entradas, opcionais, taxas, premiação, rankings e mesas. Modelos não aparecem no site nem no calendário. `Actions\Jogo\CriarDeModelo` copia tudo e recebe só nome, data e horário. No `JogoResource`, a ação "Salvar como modelo" faz o caminho inverso.

## Cálculos

- **Arrecadação** = soma de `entradas.valor` não canceladas.
- **Premiação bruta** = soma de `entradas.valor` não canceladas × fração do destino (`premiacao` 1, `metade` 0,5, `fora` 0).
- **Taxas** = soma de `jogo_taxas` calculadas (percentual sobre a premiação bruta).
- **Prize pool** = premiação bruta − taxas.
- **Fora da premiação** = arrecadação − premiação bruta.
- **Prêmio da posição** = prize pool × percentual. Sobra de arredondamento vai para o 1º.
- **Saldo do jogador** = investido − prêmio − pago + recebido. Positivo: deve ao caixa. Negativo: o caixa deve a ele.

Referência (Poker Night #18): 22 entradas, premiação bruta R$ 2.100; taxa "casa" 10% = R$ 210; prize pool R$ 1.890 → 945 / 567 / 378.

Detalhes da `Calculadora` (tudo em centavos inteiros):
- A fração do destino vale sobre a soma de cada destino, não lançamento a lançamento.
- **Jogadores** (taxa por jogador e regra de premiação) = quem tem pelo menos um buy-in válido. **Entradas** (taxa por entrada) = buy-ins, rebuys e add-ons; opcionais não contam.
- Taxa percentual arredonda meio centavo para cima (7,5% de R$ 606 = R$ 45,45). Taxa por unidade usa quantidade mínima 1.
- Prize pool não fica negativo: se as taxas passarem da premiação bruta, ele é zero.
- Prêmios: cada posição leva prize pool × percentual, para baixo; a sobra (centavos) vai para o 1º, quando os percentuais somam 100%. Premiação pela regra usa a faixa do número de jogadores; manual usa os percentuais do jogo. Cash game não tem prêmios.
- **Simulação** (prize pool estimado): N jogadores com o buy-in padrão, N rebuys e N add-ons padrão, mais os opcionais obrigatórios (por jogador ou junto de cada entrada).

## Fechamento e acertos

Com pagamentos registrados durante a noite, quase tudo se acerta pelo caixa. O fechamento mostra o saldo de cada um e sugere quem paga quem para o que sobrar (`Services\Jogo\Acertos`):

1. Credores = saldos negativos (o caixa deve). Devedores = saldos positivos.
2. Primeiro, devedores (ou combinações pequenas) que fecham exatamente o saldo de um credor.
3. Depois, guloso: maior devedor paga o maior credor aberto até zerar um dos dois.
4. Se o caixa tiver dinheiro, ele entra como credor/devedor intermediário (recebedor nulo).

O jogo pode ser finalizado com saldos em aberto: viram pendências do jogador, aparecem em "Valores pendentes" no painel e são pagas depois pela ação de pagamento.

## Travas

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
| `Services\Jogo\Acertos` | Sugestão de quem paga quem |
| `Services\Ranking\Pontuacao` | Pontos por ranking e classificação |
| `Actions\Jogo\RegistrarEntrada`, `RegistrarPagamento`, `RegistrarEliminacao`, `RegistrarCashOut`, `TrocarLugar` | Ações no jogador |
| `Actions\Jogo\DesfazerAcao` | Cancela a última ação com motivo |
| `Actions\Jogo\EncerrarRebuys`, `FecharEntradas`, `FinalizarJogo` | Fases e status |
| `Actions\Jogo\CriarDeModelo` | Novo jogo a partir de um modelo |

## Blinds e relógio (v2)

Na v1 o operador digita nível e blinds (`jogos.nivel_atual`, `blinds_atual`) e encerra rebuys no botão. Na v2:

| Tabela | Colunas principais | Observações |
|---|---|---|
| `estruturas_blinds` | nome, descricao, ativo | Estruturas reutilizáveis ("Turbo 15 min", "Deepstack 20 min") |
| `estrutura_niveis` | estrutura_id, ordem, small_blind, big_blind, ante, duracao_minutos, e_intervalo, nome_intervalo | Ante 0 = sem ante. Intervalo é um nível especial |

`jogos` ganha `estrutura_blinds_id`, `nivel_iniciado_em`, `relogio_pausado_em`, `segundos_restantes`. Relógio na faixa ao vivo e em tela cheia para TV; avança sozinho e, no intervalo, sugere **Encerrar rebuys**.

## Pontos em aberto

- Operador registra eliminação? (assumido: sim)
- Valores de pontuação dos rankings: definir depois (estrutura pronta).
