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
- **Site** → guard `web`, tabela `usuarios`, model `App\Models\Usuario`. Entra pelo usuário (`usuarios.login`, sem `@`) ou pelo e-mail, num campo só, e a senha; os dois em minúsculas, então a caixa não importa. Só quem tem `acessa_site` e está ativo (excluído também não entra). 5 tentativas erradas por login+IP bloqueiam por 1 minuto. Esqueceu a senha: o organizador troca no painel (sem recuperação por e-mail na v1).
- **Contas do clube** (`config/clube.php`, `ContasDoClubeSeeder`): quem administra ganha os dois logins (site e painel) com o mesmo usuário: um nome (`FulanoDeTal`) ou um e-mail (o login do painel aceita `@`; no site, o e-mail vai para `email`). Desenvolvedor fica no grupo Desenvolvedor dos dois lados (no site, com todas as permissões, mesmo se o Admin perder alguma); os admins, no Admin.
- **Trocar a senha**: no painel, **Perfil** (`App\Filament\Auth\Perfil`: nome e senha, com a senha atual). O site é só para o torneio e não tem tela de conta: a senha do site muda no painel, em Sistema → Usuários do site.
- **Sessão** de 12 h parada (`SESSION_LIFETIME=720`); cada toque e o poll da tela ao vivo renovam a contagem. Sessão vencida no meio de um clique recarrega a página (sem o aviso em inglês do Livewire).
- **Continuar no site** (middleware `site.acesso`, `App\Http\Middleware\AcessoDoSite`, também em cada clique do Livewire): conta desativada no painel (`ativo` ou `acessa_site` desligado) sai na hora, mesmo com "Lembrar acesso". Sem a permissão `site.fora_da_noite` (o Operador), o site só mostra o torneio dentro da **janela** dele (`App\Support\JanelaDoSite`):
  - Abre 3 h antes do início (data + horário; sem horário, o começo do dia) e fecha 3 h depois do **término previsto** (`jogos.horario_fim`; antes do horário de início = no dia seguinte; sem término, 12 h depois do início). Jogo em andamento fica aberto até finalizar, mesmo passando da hora; rascunho e cancelado nunca abrem.
  - Dentro da janela, o começo do site e outro jogo levam para a etapa do jogo (agendado: conferência; em andamento: ao vivo; finalizado: resultado). Com mais de um jogo na janela, vai para a lista na aba dele.
  - Fora da janela a pessoa **continua logada** e fica em "Nenhum torneio agora" (`/sem-torneio`), que diz qual é o próximo torneio e a que horas o site abre; ATUALIZAR leva ao jogo assim que a janela abre. Quem tem `site.fora_da_noite` (Admin, Desenvolvedor) usa o site a qualquer hora.

Permissões dos dois lados com `spatie/laravel-permission` (v6), usando o `guard_name` para separar painel e site. Tabelas renomeadas em `config/permission.php`. Grupo = conjunto de permissões; cada conta pode ter grupos e permissões avulsas.

| Tabela | Colunas principais | Observações |
|---|---|---|
| `seguranca` | nome, login (único), password, ativo, ultimo_acesso, remember_token, timestamps | Contas do painel |
| `usuarios` | nome, apelido, telefone, foto, acessa_site, login, email, password, pode_ser_dealer, pode_ser_floor, ativo, observacoes, created_at (data de cadastro), updated_at, deleted_at, remember_token | Todas as pessoas. Grupo via `modelo_grupos` (spatie), não é coluna. Jogador fica sem grupo de site e sem `acessa_site`. Jogos, pontos e dinheiro se ligam ao `id` daqui |
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
`{jogos,modelos,estruturas,rankings,usuarios,seguranca,grupos,mesas,eventos,alugueis,temporadas,acessos}.{ver,criar,editar,excluir}` (`usuarios.*` = Cadastros → Jogadores; `acessos.*` = Sistema → Usuários do site: e-mail, senha e grupos do site), `relatorios.ver`, `relatorios.exportar`, `financeiro.ver`, `configuracoes.editar`, `pagamentos.registrar` (pagamento antecipado na aba Confirmados), `pagamentos.antecipado` (marcar quem pagou antes: bônus de fichas), `jogos.andamento`, `jogos.excluir_tudo` (excluir qualquer jogo com o que foi lançado nele; só o Desenvolvedor começa com ela). Sem ela, o painel só exclui jogo em rascunho e sem entradas; o resto se cancela. Cada resource do Filament tem uma policy que consulta essas permissões.

**Permissões do site** (`guard_name = web`):
- Operador: `entradas.registrar`, `pagamentos.registrar`, `eliminacoes.registrar`, `jogadores.confirmar`, `usuarios.cadastro_rapido`.
- Só Admin: `mesas.organizar`, `jogo.fases`, `relogio.controlar` (v2), `jogo.finalizar`, `acoes.desfazer`, `jogo.corrigir` (voltar fase e desfazer pagamento/acerto depois de finalizar; dá para passar a outro grupo em Grupos), `estrutura.editar` (entradas e opcionais), `taxas.editar`, `financeiro.ver`, `pagamentos.antecipado` (marcar quem pagou antes: bônus de fichas), `site.fora_da_noite` (usar o site sem jogo em andamento).

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
| `jogos` | e_modelo, nome_modelo, modelo_id, modalidade, temporada_id, nome, data, horario, status, fase, floor_id, metodo_premiacao, lugares_por_mesa, tamanho_mesa_final, time_chip_fichas, time_chip_ate_nivel, estrutura_blinds_id, nivel_atual, blinds_atual, relogio_nivel_id, relogio_segundos, relogio_desde, relogio_iniciado_em, valor_ficha, observacoes, iniciado_em, intervalo_aberto_em, entradas_fechadas_em, finalizado_em | `e_modelo = true` = torneio modelo (só para copiar; fora do site e do calendário). `modelo_id` → `jogos`: o modelo de onde o jogo saiu (tipo de evento das Métricas). `modalidade`: `torneio` / `cash_game`. `floor_id` → `usuarios` |
| `jogo_itens` | jogo_id, tipo, nome, valor, fichas, percentual_premiacao, compravel_com (json), obrigatorio, pago_antes, limite_por_jogador, padrao, ativo, ordem | O que o jogador pode comprar. `tipo`: `buyin` / `rebuy` / `addon` / `opcional`. `padrao = true` no buy-in, rebuy e add-on que já vêm no cadastro (não se apagam, só se desativam); variações como "Rebuy duplo" são linhas extras do mesmo tipo. `percentual_premiacao`: quanto do valor vai para a premiação, de 0 a 100 (entradas 100 por padrão, opcionais 0). Opcional: `compravel_com` = `[buyin, rebuy, addon]` (junto de cada entrada) ou `[jogador]` (1 por jogador); `pago_antes`: dá para marcar quem pagou antes (taxa da pizza), e ele entra junto do buy-in |
| `jogo_taxas` | jogo_id, nome, calculo, valor, quantidade, ordem | Tiradas da premiação antes de dividir (dealers, casa…). `calculo`: `fixo` / `percentual` / `por_unidade` / `por_jogador` / `por_entrada` |
| `jogo_premiacoes` | jogo_id, posicao, percentual, valor | Só torneio. `valor` gravado ao fechar entradas |
| `jogo_mesas` | jogo_id, nome, dealer_id, lugares, ordem | A mesa do torneio ("Mesa 1", "Mesa 2"…), sem ligação com o cadastro `mesas` (que é dos aluguéis e reservas): o torneio pode ter mais mesas do que as cadastradas. `dealer_id` → `usuarios` com `pode_ser_dealer` |
| `jogo_ranking` | jogo_id, ranking_id | Rankings em que o jogo vale ponto (torneio ou cash game) |
| `participantes` | jogo_id, usuario_id, jogo_mesa_id, lugar, status, pagou_antes, posicao_final, premio, fichas_finais, cash_out | Únicos: (jogo_id, usuario_id) e (jogo_mesa_id, lugar). `status`: `confirmado` / `jogando` / `eliminado` / `saiu` |
| `participante_opcionais` | participante_id, jogo_item_id | Opcionais que o jogador pagou antes (`Participante::opcionaisPagos`): vão junto do buy-in. Único: (participante_id, jogo_item_id) |
| `entradas` | uuid (único), jogo_id, participante_id, jogo_item_id, tipo, valor, fichas, time_chip, percentual_premiacao, vinculada_a, registrado_por, cancelada_em, cancelada_por, motivo_cancelamento | Cada item comprado. `valor`, `fichas` e `percentual_premiacao` copiados do item no momento; `time_chip`: fichas a mais do time chip (só no buy-in dentro do prazo, senão 0); `vinculada_a` liga o opcional à entrada com que foi comprado. Nunca apagada |
| `pagamentos` | uuid (único), jogo_id, participante_id, direcao, valor, forma, observacao, registrado_por, cancelado_em, cancelado_por, motivo_cancelamento | `direcao`: `jogador_pagou` / `jogador_recebeu`. `forma`: `pix` / `dinheiro` / `cartao` / `outro` |
| `eliminacoes` | jogo_id, participante_id, nivel, definitiva, voltou_com, registrada_por, cancelada_em, cancelada_por, motivo_cancelamento | Um jogador pode cair, voltar com rebuy e cair de novo. `voltou_com` → `entradas`: o rebuy com que voltou de uma eliminação provisória (ela fica como histórico) |
| `ranking_pontos` | ranking_id, jogo_id, usuario_id, posicao, pontos, detalhe (json) | Gravado ao finalizar; mudar a regra depois não altera pontos já dados |
| `acertos` | jogo_id, de_participante_id, para_participante_id, valor, pago, pago_em | Sugestão do fechamento. Recebedor nulo = caixa |

### Agenda e aluguéis

| Tabela | Colunas principais | Observações |
|---|---|---|
| `eventos` | tipo, nome, data, hora_inicio, hora_fim, responsavel_id, jogo_id, observacoes | `tipo`: `jogo` / `aluguel` / `reserva` / `bloqueio`. Criar um jogo cria o evento |
| `evento_mesa` | evento_id, mesa_id | Um aluguel, reserva ou bloqueio pode ocupar várias mesas do cadastro. O evento do jogo não ocupa mesas: torneio e aluguel não se cruzam no aviso de mesa ocupada |
| `alugueis` | evento_id, cliente, valor, status, pago_em | `status`: `reservado` / `confirmado` / `pago` / `cancelado` |

Regras da agenda (`Actions\Agenda\SalvarEvento`, `Services\Agenda\Conflitos`):
- O evento do jogo vem do cadastro do jogo (`EventoDoJogo`) e não se edita pelo calendário. Aluguel, reserva e bloqueio se criam e editam no calendário (aluguel também na tela de aluguéis).
- Aluguel se chama "Mesa alugada — Cliente" e tem um `alugueis` ligado. Virar reserva ou bloqueio apaga o aluguel; excluir o aluguel apaga o evento.
- Marcar pago grava `pago_em` uma vez (editar de novo não muda a data); sair de pago limpa.
- Horário: sem início = dia todo; sem fim = até o fim do dia; fim antes do início = passa da meia-noite (20:00–01:00).
- Mesa ocupada: outro evento no mesmo dia, com a mesma mesa e horário que se cruza (encostar não conta: 15:00–19:00 e 19:00–23:30 não se cruzam). Só avisa; aluguel cancelado não ocupa mesa e sai do calendário.

## Fases de um torneio

Durante `status = em_andamento`, o torneio passa por três fases (`jogos.fase`), cada uma aberta por um botão do operador:

1. `rebuys` (início até o intervalo): buy-in e rebuy. Eliminação é **provisória**: o jogador pode voltar com rebuy (volta para o mesmo lugar), na hora ou depois.
2. `intervalo` — botão **ABRIR INTERVALO** (`Actions\Jogo\AbrirIntervalo`, `jogos.intervalo_aberto_em`): o add-on abre (até `limite_por_jogador`) e o **rebuy continua**: quem caiu e quis respirar volta no intervalo. A eliminação continua provisória.
3. `sem_entradas` — botão **FECHAR ENTRADAS**, no fim do intervalo: buy-in, rebuy e add-on fecham; quem estava eliminado e não voltou vira eliminação definitiva, na ordem em que caiu; `jogo_premiacoes.valor` é gravado. Daqui em diante só eliminações definitivas, e cada uma dá a posição na hora (quem cai fica em último entre os que restam).

Detalhes (`AbrirIntervalo`, `FecharEntradas`, `Services\Jogo\Posicoes`):
- Com o relógio, a tela ao vivo sugere ABRIR INTERVALO quando ele chega ao nível de intervalo e FECHAR ENTRADAS quando o intervalo acaba (só sugere: quem aperta é o Admin).
- Buy-in atrasado ainda vale no intervalo e muda o número de jogadores; por isso a posição só sai ao fechar as entradas.
- Fechar entradas só a partir do intervalo (torneio sem add-on: ABRIR INTERVALO e depois FECHAR ENTRADAS) e com pelo menos 2 jogadores com buy-in. Grava em `jogo_premiacoes` o percentual e o valor de cada posição pela `Calculadora` (faixa da regra do clube ou percentuais manuais); sem faixa para o número de jogadores, não fecha.
- Posição: N = jogadores com buy-in válido; a k-ésima eliminação definitiva, na ordem em que caíram (0, 1, 2…), fica em N − k. Quando sobra um só, ele é o 1º. `participantes.premio` = valor gravado da posição (vazio fora da premiação).
- As posições são refeitas a cada eliminação e a cada eliminação desfeita (quem caiu depois sobe uma posição).

## Lista da noite (jogadores confirmados)

- **Pré-torneio no painel** (aba **Confirmados** do jogo, `Filament\Resources\Jogos\RelationManagers\ConfirmadosRelationManager`, a partir de agendado): confirmar jogadores do cadastro, cadastrar um jogador novo (nome, apelido e telefone) e lançar o **pagamento antecipado** (buy-in, churrasco…, em Pix ou Dinheiro; o valor vem com o buy-in padrão e os obrigatórios). O pagamento abate do que o jogador vai dever. Quem pagou e não veio (não sentou) não entra no torneio: o pagamento vira crédito, a aba mostra "Não veio · devolver R$ X" e o fechamento sugere o caixa devolver. Permissões do painel: `jogos.editar` (lista), `usuarios.criar` (jogador novo) e `pagamentos.registrar` (pagamento; grava `pagamentos.registrado_no_painel_por`).
- Confirmar põe o jogador em `participantes` com status `confirmado` e sem lugar; vale com o jogo agendado ou em andamento (quem chega atrasado). Confirmar de novo não duplica. Jogador inativo não entra.
- Tirar da lista apaga a linha do participante, mas só enquanto ele não tem entrada, pagamento nem eliminação; depois disso ele fica (o caixa já o conhece).
- Cadastro rápido (`usuarios.cadastro_rapido`) cria o jogador só com nome e apelido opcional e já confirma. Se o nome ou o apelido bater com o nome ou o apelido de alguém (sem diferença de maiúsculas), não cria: o aviso manda confirmar pela lista.
- Confirmar e tirar pedem `jogadores.confirmar` no site (Operador e Admin) ou `jogos.editar` no painel.

## Mesas e início do jogo

- Montar as mesas pede `mesas.organizar` (Admin do site); o Operador só vê.
- **Sentar é entrar** (torneio): quem senta na mesa já tem o buy-in padrão (a linha marcada como padrão, com os opcionais obrigatórios; `RegistrarEntrada::buyinPadrao`). Sentado antes de começar ganha o buy-in ao **Iniciar**; quem senta com o torneio em andamento, na hora (`TrocarLugar`). Mudar de lugar não lança de novo. Outra variação (buy-in duplo): desfaz o padrão e lança a certa. Com as entradas fechadas, ninguém novo senta. No cash game cada buy-in continua lançado à mão.
- **Lugar vazio** (telas 06 e 07, quem tem `mesas.organizar`): clicar abre "Quem senta aqui" (`Livewire\Jogo\Concerns\EscolheQuemSenta`), com os confirmados que ainda não têm lugar (quem já sentou some), a busca no cadastro de quem não estava na lista (confirma e senta) e o jogador novo (cadastra, confirma e senta).
- Trocar de lugar: clicar no jogador e depois num lugar. Lugar vazio = mover; ocupado = os dois trocam (quem estava vai para o lugar antigo, ou fica sem lugar). Vale com o jogo agendado ou em andamento (quebra de mesa). Eliminado ou quem saiu não senta.
- **Sortear lugares** (modal Jogadores: botão Sortear lugares na tela 06 e JOGADORES na tela ao vivo; `SortearLugares`): marca-se quem chegou (ou todos, ou quem já está sentado, para trocar) e em quais mesas. Dá para pôr na lista alguém do cadastro ou um jogador novo, que já fica marcado. As mesas ficam equilibradas na proporção dos lugares, contando quem fica onde está, e o lugar dentro da mesa é aleatório. Livres são os lugares vagos e o de quem já saiu (eliminado, cash-out), que só guardava o lugar. Sem lugar suficiente, avisa quantos faltam ("Falta 1 lugar nas mesas escolhidas"). Vale antes e durante o jogo: no torneio em andamento quem senta pela primeira vez entra com o buy-in padrão (como sentar à mão, com time chip e bônus de quem pagou antes) e o modal mostra onde cada um senta e quantas fichas entregar. Quem caiu e não voltou não é sorteado: primeiro o rebuy (VOLTAR no painel do jogador; no intervalo, com o add-on), depois o sorteio. Com as entradas fechadas, só quem já está jogando muda (quebra de mesa). Tudo ou nada: uma regra barrada não deixa ninguém sentado pela metade. Para um lugar certo, clica-se no lugar vazio (Quem senta aqui).
- Lugares por mesa de 2 a 12, sem diminuir abaixo de quem está sentado. Dealer só quem tem "Pode ser dealer". Mesa só sai do jogo vazia. Adicionar ou tirar mesa atualiza a agenda.
- **Iniciar** (`jogo.fases`, Admin do site): só agendado e com pelo menos 2 jogadores sentados. Torneio vai para a fase `rebuys` e cada sentado entra com o buy-in padrão; cash game fica sem fase. Sentados passam a `jogando`; confirmado sem lugar continua esperando e senta depois.

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
  - Rebuy até fechar as entradas (na fase de rebuys e no intervalo); add-on só no intervalo. Os dois pedem buy-in.
  - O limite por jogador conta as entradas válidas da mesma linha (ex.: add-on 1); vazio = sem limite.
  - Opcionais: os marcados e os obrigatórios vão junto da entrada (`vinculada_a`). O "1 por jogador" entra uma vez só e também pode ser comprado sozinho; obrigatório já comprado não repete; marcado já comprado é erro.
  - No painel do jogador, cada opcional é um **cartão** ao lado do BUY-IN, REBUY e ADD-ON, para comprar na hora (o buy-in nasce ao sentar, sem a escolha dos opcionais). O "junto de cada entrada" comprado sozinho vai **um para cada entrada** que o jogador já tem (buy-in, rebuys, add-on; `RegistrarEntrada::semEntradaLivre`), contando também os marcados junto do rebuy: sem buy-in, "ainda não tem buy-in"; com todas cobertas, "já comprou … para cada entrada". Comprado sozinho, é uma ação dele (o DESFAZER tira só o opcional). O obrigatório não tem cartão, a não ser que esteja faltando.
  - Eliminado só volta com rebuy, até fechar as entradas: no painel do jogador o botão do buy-in vira **VOLTAR** ("entra como rebuy"). A eliminação provisória ganha `voltou_com` e o jogador volta a jogar, no mesmo lugar.
  - Cash game: buy-in e recompra (rebuy) a qualquer hora em andamento; sem add-on.
- **Pagamento** (`pagamentos.registrar`; `RegistrarPagamento`): valor maior que zero, jogador pagou ou recebeu, forma (Pix ou Dinheiro; Cartão e Outro ficam no enum só para o que já foi gravado, `FormaPagamento::disponiveis()`) e observação opcional. Com o jogo agendado (pagou antes), em andamento ou finalizado (pendências). Pode passar do que deve: o saldo fica negativo (o caixa devolve).
- **Eliminação** (`eliminacoes.registrar`; `RegistrarEliminacao`): só torneio, jogador jogando e com buy-in, e nunca o último em jogo (quem está sentado sem buy-in não conta; o último é o campeão). Provisória com as entradas abertas (rebuys e intervalo); definitiva depois. Guarda o nível atual.
- **Cash-out** (`eliminacoes.registrar`; `RegistrarCashOut`): só cash game, jogador com buy-in e valor da ficha no jogo. O jogador sai (`saiu`); registrar de novo corrige o valor.
- **Lugar de quem saiu**: eliminado e quem fez cash-out continuam no lugar (a mesa mostra apagado) até alguém sentar ali, e aí ficam sem lugar em vez de trocar. Diminuir a mesa tira do lugar quem já saiu; tirar a mesa ainda pede que ninguém esteja jogando nela. Quem chega atrasado e senta com o jogo em andamento passa a jogando.
- **Desfazer** (`acoes.desfazer`, só Admin; `DesfazerAcao`): com motivo, só em andamento, sempre a última ação do jogador (a mais recente entre entrada, eliminação e pagamento; opcional junto de outra entrada vai com ela; no mesmo segundo vale a ordem entrada → eliminação → pagamento).
  - Entrada leva junto os opcionais comprados com ela e só se desfaz até Fechar entradas. Rebuy que trouxe o jogador de volta devolve a eliminação (provisória: as entradas ainda estão abertas).
  - Eliminação desfeita: o jogador volta a jogar e as posições são refeitas.
  - Cash-out não se desfaz: registrar de novo corrige.

### Fichas entregues e time chip

- Cada entrada copia as fichas do item na hora do lançamento (`entradas.fichas`). As **fichas entregues** ao jogador são a soma das entradas e opcionais válidos mais o time chip e o bônus do pagamento antecipado (`Entradas::porJogador()`: `fichas`, `timeChip` e `antecipado` em `EntradasDoJogador`). Aparecem no painel do jogador (bloco "Fichas entregues", com "buy-in 20.000 · time chip 10.000 · rebuys 20.000 · add-on … · opcionais …") e na coluna **Fichas** do Resumo (tela 10). Entrada desfeita sai da soma.
- O aviso de cada lançamento diz o que entregar: "Buy-in registrado para Ana: entregar 30.000 fichas (10.000 do time chip)." (`RegistrarEntrada::fichasDoLancamento`: a entrada, o time chip e os opcionais que foram junto). O "Quem senta aqui" também avisa ("Sentar já lança o buy-in (Buy-in · R$ 100): entregar 30.000 fichas (10.000 do time chip)").
- **Time chip** (só torneio): fichas a mais para quem entra cedo, ex.: "10.000 fichas até o fim do nível 1". No cadastro, aba Blinds: `jogos.time_chip_fichas` (vazio ou 0 = sem time chip) e `time_chip_ate_nivel` (padrão 1). Para dar mais tempo de chegar, aumente os minutos do nível 1 na estrutura de blinds.
  - Quem ganha: o **buy-in** lançado até o fim daquele nível (`Services\Jogo\TimeChip::valeAgora`), gravado na própria entrada (`entradas.time_chip`). Rebuy, add-on e opcional nunca ganham. Antes de iniciar e com o relógio parado no nível 1, vale. Com estrutura, conta o relógio (o intervalo depois do nível do prazo já é fora); sem estrutura, o nível digitado na tela ao vivo (vazio = nível 1).
  - Como o buy-in nasce ao sentar (ao Iniciar, ou na hora com o jogo em andamento), quem senta no prazo ganha e quem chega depois não. Desfazer o buy-in leva o time chip junto.
  - A Conferência (tela 04) mostra "Time chip: 10.000 fichas até o fim do nível 1" ou "Não tem".
  - O time chip não é dinheiro: não muda arrecadação, prize pool nem saldo.
- **Bônus do pagamento antecipado** (só torneio): quem pagou o buy-in antes ganha fichas a mais junto do buy-in, ex.: 20.000. No cadastro, aba Blinds, seção Pagamento antecipado: `jogos.antecipado_fichas` (vazio ou 0 = sem bônus).
  - Quem ganha é quem está **marcado** como "pagou antes" (`participantes.pagou_antes`), sem olhar data nem valor: quem marca decide (`Actions\Jogo\MarcarPagamentoAntecipado`, permissão `pagamentos.antecipado` no painel e no site, Admin e Desenvolvedor; jogo agendado ou em andamento). Marca-se no painel, aba Confirmados (botão **Pagou antes** na linha, ou a caixa "Pagamento antecipado do buy-in" no modal do Pagamento, que lança os dois juntos), e no site, na lista da noite (tela 05) e no painel do jogador (Marcar / Desmarcar).
  - O bônus fica no **buy-in** (`entradas.antecipado`), como o time chip: quem ainda não sentou ganha ao sentar; quem já sentou, o buy-in ganha (ou perde, ao desmarcar) na hora, e o aviso diz "entregar 20.000 fichas a mais". Rebuy, add-on e opcional nunca ganham; desfazer o buy-in leva junto, e o buy-in refeito ganha de novo se a marca continuar.
  - O "Quem senta aqui" mostra "Pagou antes · +20.000" em quem está marcado e diz "entregar 20.000 fichas; quem pagou antes, mais 20.000". Sentar com o torneio em andamento avisa na tela ao vivo: "Ana sentou na Mesa 1 · Lugar 5, pagou antes: entregar 40.000 fichas (20.000 do pagamento antecipado)." O painel do jogador e o Resumo mostram "Pagou antes" e o bônus nas fichas entregues; a Conferência, as fichas e quantos estão marcados.
  - Marcar e desmarcar ficam no histórico do jogo. O bônus não é dinheiro: o pagamento antecipado continua sendo um pagamento normal (abate do que ele deve).
- **Opcionais pagos antes** (taxa da pizza…; torneio e cash game): o opcional com **Pago antes** no cadastro do jogo (`jogo_itens.pago_antes`, aba Dinheiro, seção Opcionais) pode ser marcado em cada jogador que já pagou, e ele **entra junto do buy-in**, com as fichas, quando o jogador sentar (`participante_opcionais`; `Actions\Jogo\MarcarOpcionalPagoAntes`, a mesma permissão `pagamentos.antecipado`; jogo agendado ou em andamento).
  - Marca-se no painel, aba Confirmados (ação **Opcionais pagos** na linha, ou as caixas "Opcionais pagos antes" no modal do Pagamento, que lança o pagamento e marca juntos), e no site, na lista da noite (tela 05, um botão por opcional embaixo do nome) e no painel do jogador (Marcar / Desmarcar).
  - No buy-in (`RegistrarEntrada`), os marcados vão junto como os obrigatórios (`vinculada_a`): já comprado na hora (o "1 por jogador" pelo cartão) fica de fora sem erro. Rebuy e add-on não levam. Desfazer o buy-in leva junto, e o buy-in refeito traz de novo se a marca continuar. Opcional que deixou de ser pago antes (ou foi desativado) não vai mais, mesmo com a marca.
  - Já sentado: marcar lança na hora, junto do buy-in (no painel, a entrada fica sem `registrado_por`; o histórico diz quem); desmarcar tira o que tinha ido junto do buy-in. O que ele comprou na hora pelo cartão continua (sai pelo DESFAZER). Com as entradas fechadas, não muda mais.
  - O aviso diz o que entregar, com cada opcional: "Ana sentou na Mesa 1 · Lugar 5: entregar 60.000 fichas (40.000 de Taxa Comida)." O "Quem senta aqui" e o modal Jogadores mostram o selo "Taxa Comida · +40.000"; a Conferência, quantos estão marcados em cada um. Marcar não lança dinheiro: o pagamento é lançado no Pagamento, como qualquer outro.

## Cash game

Sem eliminação, posição ou premiação. Ações: entrada (buy-in/recompra), pagamento, cash-out.

- Cash-out = fichas finais × `jogos.valor_ficha`.
- Resultado = cash-out − investido.
- Entradas, opcionais e taxas iguais ao torneio.
- Conferência: soma dos cash-outs = valor da premiação (o que está nas fichas). Se não bater, mostra a diferença antes de finalizar.

## Dinheiro do jogo: entradas, opcionais e taxas

Tudo definido no cadastro do jogo (ou no modelo), em três blocos. Durante o jogo, só o Admin do site (`estrutura.editar`, `taxas.editar`) muda algo, até **Fechar entradas**. O que já foi lançado mantém o valor, as fichas e o percentual da hora.

**1. Entradas** — buy-in, rebuy e add-on já vêm no cadastro, um de cada, com valor (R$) e fichas. O **+** de cada um adiciona uma variação do mesmo tipo (buy-in duplo, rebuy duplo…), com nome, valor e fichas próprios. Cada linha tem limite por jogador opcional (ex.: buy-in 1, add-on 1, rebuy sem limite). Torneio sem rebuy ou sem add-on: desativa a linha padrão. Cada linha diz quanto vai para a premiação (100% por padrão).

**2. Opcionais** — o que mais o jogador pode comprar: opcional de fichas, churrasco… Cada um tem nome, valor, fichas (pode ser 0), se é comprado junto de cada buy-in/rebuy/add-on ou 1 por jogador, se é obrigatório, e quanto vai para a premiação (0% por padrão).

**Premiação de cada item** (`percentual_premiacao`, entradas e opcionais): no painel, os atalhos **100%**, **50%** e **0%**, e **Outro** para digitar qualquer percentual de 0 a 100 (ex.: 75%). O que não vai para a premiação fica com a casa ("fora da premiação"). A Conferência mostra o percentual de cada opcional e das entradas que não vão 100%.

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

Entradas: 1 buy-in, rebuys ilimitados, 1 add-on. Opcionais: "Opcional" R$ 10 · 20k junto de cada entrada, 50% na premiação; "Churrasco" R$ 30 · 20k, 1 por jogador, 0%. Taxas: casa R$ 190 (fixo), dealers R$ 430.

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

`jogos.e_modelo = true` guarda um modelo com entradas, opcionais, taxas, premiação, rankings e mesas. Modelos não aparecem no site nem no calendário. `Actions\Jogo\CriarDeModelo` copia tudo e recebe só nome, data e horário; o jogo novo guarda o modelo em `modelo_id` (o Duplicar mantém). No `JogoResource`, a ação "Salvar como modelo" faz o caminho inverso.

## Cálculos

- **Arrecadação** = soma de `entradas.valor` não canceladas.
- **Premiação bruta** = soma de `entradas.valor` não canceladas × `percentual_premiacao` de cada uma. No torneio, desce para o real inteiro (os centavos ficam fora da premiação, com a casa).
- **Taxas** = soma de `jogo_taxas` calculadas (percentual sobre a premiação bruta).
- **Prize pool** = premiação bruta − taxas.
- **Fora da premiação** = arrecadação − premiação bruta.
- **Prêmio da posição** = prize pool × percentual. Do 2º para baixo o prêmio desce para o múltiplo de R$ 5 com "Arredondar prêmios para R$ 5" (Configurações, ligado por padrão), senão para o real; a sobra vai para o 1º. Torneio não tem centavos: nem na premiação bruta, nem na taxa em %, nem nos prêmios.
- **Saldo do jogador** = investido − prêmio − pago + recebido. Positivo: deve ao caixa. Negativo: o caixa deve a ele. No cash game o prêmio é o cash-out. `Services\Jogo\Saldos` soma em centavos a partir das entradas e pagamentos válidos; sem pagamentos, a soma dos saldos é o que fica com a casa (taxas + fora da premiação).

Referência (Poker Night #18): 22 entradas, premiação bruta R$ 2.100; taxa "casa" 10% = R$ 210; prize pool R$ 1.890 → 950 / 565 / 375 (arredondado para R$ 5; sem arredondar, 945 / 567 / 378).

Detalhes da `Calculadora` (tudo em centavos inteiros):
- O percentual vale sobre a soma dos lançamentos com o mesmo percentual, não lançamento a lançamento (3 opcionais de R$ 10 a 75% = R$ 22,50 → R$ 22 no torneio).
- **Jogadores** (taxa por jogador e regra de premiação) = quem tem pelo menos um buy-in válido. **Entradas** (taxa por entrada) = buy-ins, rebuys e add-ons; opcionais não contam.
- Taxa percentual: no torneio, arredonda para o real (meio real para cima: 7,5% de R$ 606 = 45,45 → R$ 45; de R$ 610 = 45,75 → R$ 46); no cash game, meio centavo para cima. Taxa por unidade usa quantidade mínima 1.
- Prize pool não fica negativo: se as taxas passarem da premiação bruta, ele é zero.
- Prêmios: cada posição leva prize pool × percentual, para baixo; a sobra vai para o 1º, quando os percentuais somam 100%. Do 2º para baixo desce para o múltiplo de R$ 5 (`App\Support\ArredondarPremios`, chave `premios.arredondar`) ou, desligado, para o real, e o 1º fecha a conta: ele só sai múltiplo de R$ 5 se o prize pool for. Prêmio que viraria zero desce para o real (R$ 4,50 → R$ 4); premiação manual que não soma 100% arredonda o 1º também. Vale ao fechar as entradas, quando os prêmios são gravados. Premiação pela regra usa a faixa do número de jogadores; manual usa os percentuais do jogo, que o painel só salva somando 100% (as sugestões do cadastro, concentrada e equilibrada, saem em múltiplos de 5, com pelo menos 5% por posição; acima de 20 posições, de 1 em 1). Cash game não tem prêmios.
- **Simulação** (prize pool estimado): N jogadores com o buy-in padrão, N rebuys e N add-ons padrão, mais os opcionais obrigatórios (por jogador ou junto de cada entrada).

## Correções e histórico

Mexida errada se corrige, mas fica registrada. Tudo pede **motivo** e entra no **histórico do jogo** (tabela `alteracoes`, `Services\Jogo\Historico`; aba **Histórico** do jogo no painel e, para quem corrige, o fim do Fechamento no site). Permissão do site `jogo.corrigir` (o Admin tem; pode ir para outro grupo).

- **Voltar a fase** (`Actions\Jogo\VoltarFase`, tela ao vivo): do intervalo para os rebuys (o add-on fecha; os já lançados continuam) e **REABRIR ENTRADAS** (sem entradas → intervalo: buy-in, rebuy e add-on valem de novo, quem caiu e não voltou volta a poder voltar, as posições e os prêmios gravados saem e são refeitos no próximo FECHAR ENTRADAS; o histórico guarda os que estavam). Só em andamento.
- **Desfazer pagamento** (`Actions\Jogo\DesfazerPagamento`): qualquer pagamento lançado à mão, não só o último, também depois de finalizar (no painel do jogador, botão Desfazer na linha) e no pré-torneio do painel (aba Confirmados, `pagamentos.registrar`). Depois de finalizar, os acertos em aberto são refeitos. Pagamento de um acerto feito (`pagamentos.acerto_id`) não sai sozinho.
- **Mudar o cadastro com o jogo em andamento** (painel, permissão `jogos.andamento`: Admin e Desenvolvedor; `Actions\Jogo\RefazerPeloCadastro`, chamada pelo `EditJogo` depois de salvar, na mesma transação): as abas Dinheiro, Premiação e Mesas abrem (modalidade e blinds continuam travadas: o relógio não muda no meio). Ao salvar, as entradas válidas já lançadas passam a valer o valor, as fichas e o percentual da premiação do item como está agora; no cash game o cash-out segue o valor da ficha; com as entradas fechadas, os prêmios de cada posição são regravados (`Posicoes::gravarPremios`, o mesmo do FECHAR ENTRADAS) e as posições refeitas. O que cada um deve e o prize pool saem das entradas, então acompanham. Linha de entrada ou opcional com entrada lançada não se apaga (desative); mesa não fica com menos lugares que o último ocupado. Vai para o histórico como **Cadastro alterado** (correção), com o que mudou ("Rebuy R$ 100 → R$ 120 (3 entradas); prize pool R$ 1.890 → R$ 1.950"). Se o recálculo esbarrar numa regra (ex.: nenhuma regra de premiação cobre os jogadores), nada fica gravado.
- **Desfazer acerto** (`Actions\Jogo\DesfazerAcerto`, fechamento): FEITO marcado errado; os pagamentos do acerto ficam cancelados, ele volta a estar em aberto e a lista é refeita.
- Também ficam no histórico, sem motivo: início, abrir o intervalo, fechar as entradas, resultado, acerto feito e finalizar; e todo desfazer da noite (`DesfazerAcao`), com o motivo. Quem fez pelo site fica em `usuario_id`; pelo painel, em `seguranca_id` ("dev (painel)").

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

Depois de finalizar, os acertos ainda não feitos acompanham os saldos (`Acertos::refazerEmAberto`): um pagamento lançado pelo painel do jogador (pendência acertada por fora) refaz a lista, e o FEITO confere se o acerto ainda bate com os saldos (quem paga ainda deve o valor, quem recebe ainda tem a receber). Se não bate, a lista é refeita e nada é lançado: o mesmo dinheiro nunca entra duas vezes. Os acertos feitos ficam como histórico.

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
| `Actions\Jogo\AbrirIntervalo`, `FecharEntradas`, `FinalizarJogo` | Fases e status |
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
- **Sugestões da fase:** quando o relógio está num intervalo e o torneio ainda está nos rebuys, quem muda a fase (`jogo.fases`) vê acima da mesa "Intervalo: hora de abrir o intervalo (add-on, e quem caiu pode voltar)" com ABRIR INTERVALO; com o intervalo aberto e o relógio já depois dele, "O intervalo acabou: hora de fechar as entradas" com FECHAR ENTRADAS. Os dois abrem o mesmo modal da fase. Só sugerem: nada muda sozinho.
- Celular: a barra de baixo ganha o relógio na frente (nível e tempo; Admin abre o modal por ela) e fica com quatro células (relógio, blinds sem o ante, entradas e prize pool).

**Link do relógio (v3, etapa 3, feita; no lugar da tela da TV da v2):** cada jogo tem um código (`jogos.relogio_codigo`, 8 letras e números sem os que confundem, como `k7m2q9xp`), criado junto com o jogo; cópia e modelo ganham um novo. O link `/relogio/{codigo}` (`RelogioPublicoController`) abre **sem login** e só para ver, na TV, no tablet, em outro celular ou no notebook.

- **Mostra só o relógio:** o nome do jogo, o nível, o tempo, os blinds e o ante (no intervalo, os da volta), o que vem depois e o intervalo (em tempo e em níveis: "Intervalo em 2 níveis · 29:55"; no intervalo, "Agora"; sem intervalo pela frente, avisa). Nada de dinheiro, jogadores, entradas ou stack médio.
- **Antes de começar:** data, horário, nome e "O relógio começa quando o torneio for iniciado · nível 1: 10 / 20 · 15 min". **Depois de finalizar:** "Fim de jogo" (sem prêmios). Cancelado: "Jogo cancelado". Rascunho, modelo e link trocado: 404 com "Este link não vale mais".
- Torneio sem estrutura (e cash game): o nível e os blinds digitados na tela ao vivo.
- **Atualiza sozinho:** a página carrega uma vez; o motor busca `/relogio/{codigo}/estado` a cada 2 s, uma rota **sem sessão, cookie nem CSRF** (a tela pode ficar horas aberta, até sem internet, sem "página expirou"). O JSON traz o status, a `chave` (muda quando o jogo começa, acaba ou muda o nível digitado: a página recarrega) e o pacote do relógio. Sem internet, segue contando (etapa 2).
- **Onde pegar o link:** botão **TV** da tela ao vivo (modal "Relógio nas outras telas": o link, COPIAR LINK, ABRIR e, para quem tem `relogio.controlar`, GERAR OUTRO LINK, que troca o código pelo `Actions\Jogo\TrocarLinkDoRelogio` e faz o antigo parar de abrir) e a **Conferência** (tela 04), para deixar a TV pronta antes de começar. `/jogos/{jogo}/tv` (endereço antigo) leva para o link.
- O copiar usa `$copiar` (`resources/js/site/copiar.js`): área de transferência em https e localhost, e o jeito antigo no http da rede local.

**Tela do relógio do gerenciador e o AVISO (v3, etapa 4, feita):** `GET /jogos/{jogo}/relogio` (`jogos.relogio`, Livewire `Jogo\ControleDoRelogio`, `jogo.etapa:ao-vivo`), aberta pelo botão **RELÓGIO** da tela ao vivo (torneio).

- A tela toda é o relógio: o mesmo painel do link (nível, tempo, blinds e ante, o que vem depois, intervalo em níveis e tempo), ANTERIOR, PAUSAR/CONTINUAR (INICIAR na primeira vez), PRÓXIMO, −5, −1, +1 e +5 min, **AVISO** e LINK DA TV (copia o link). Em pé: relógio em cima e botões embaixo; deitado (celular, tablet, notebook, PC): relógio à esquerda e botões à direita. Sem rolar, com a tela acesa onde o navegador deixa.
- Quem não tem `relogio.controlar` (o Operador) vê o relógio sem os botões. Torneio sem estrutura: o nível digitado e só o AVISO.
- **AVISO** (`Actions\Jogo\DispararAviso`, `relogio.controlar`, só em andamento): soma um em `jogos.avisos` e guarda `aviso_em`. Toca na hora no aparelho de quem apertou (o clique libera o som) e, nas telas do link, na próxima busca (até 2 s; no teste, 0,7 s): o motor toca quando o número sobe e o aviso tem até 30 s (uma tela que volta da falta de internet não toca aviso velho; abrir ou recarregar a página também não). O cartão do relógio pisca em vermelho com a etiqueta "AVISO" por 6 s, para quem está com o som desligado. O som (`resources/js/site/som.js`) é um "ding-dong" três vezes, diferente da campainha da troca de nível.
- **Som na troca de nível** (`resources/js/site/tv.js`, Web Audio, sem arquivo): três notas quando o motor do relógio passa para um nível novo, pelo tempo ou por PRÓXIMO em outro aparelho (uma vez só por nível). O relógio, os blinds, o que vem depois e o "Intervalo em" (tempo e níveis, ex.: "Intervalo em 2 níveis · 28:30") vêm do motor (`<x-site.poker.painel-relogio>`), então seguem andando sem internet. Desligado por padrão; ligado fica guardado no aparelho. O navegador só deixa tocar depois de um toque na página: com o som ligado e sem toque, a tela pede "Toque na tela para ligar o som".
- **Tela cheia** pelo botão (ou F11 da TV); os botões (no celular, só os ícones) e o cursor somem depois de 3 s sem mexer. A tela fica acesa (Screen Wake Lock) onde o navegador deixa (https ou localhost); fora disso, vale a configuração da TV.

Com a etapa 4, a v2 (estruturas de blinds e relógio) está completa.

## Pontos em aberto

- Operador registra eliminação? (assumido: sim)
- Valores de pontuação dos rankings: definir depois (estrutura pronta).
