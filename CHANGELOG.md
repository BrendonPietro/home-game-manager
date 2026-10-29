# Changelog

Registro das atualizações do sistema do Rodrigues Home Game, organizadas por:

- Feat: nova funcionalidade;
- Fix: correção de bug;
- Style: estilo de uma tela ou componente;
- Refactor: refatoração;
- Test: testes ou experimentos;
- Docs: documentação;
- Chore: manutenção.

## 0.7.3

v2, etapa 4: tela cheia para a TV. Com ela, a v2 (estruturas de blinds e relógio) está completa.

### Feat
- Tela da TV em `/jogos/{jogo}/tv` (botão **TV** na tela ao vivo, numa aba nova), para qualquer conta do site: nível, relógio grande, blinds e ante, o que vem depois, prize pool, premiação (até agora ou gravada, com quem está em cada posição), jogando, entradas, stack médio e o tempo até o intervalo. No intervalo mostra os blinds da volta.
- Som na troca de nível (três notas, gerado no navegador): quando o relógio zera ou quando o nível muda por outro aparelho. Desligado por padrão; o aparelho lembra.
- Tela cheia, botões e cursor que somem sem mexer e tela acesa (onde o navegador deixa).
- Antes de começar, a TV mostra data, horário e confirmados; depois de finalizar, "Fim de jogo" com os premiados.

### Test
- `Site\TvTest` (noite #18, intervalo, prêmios gravados, quem vê, jogo sem estrutura, antes e depois, jogo fora do site).

## 0.7.2

v2, etapa 3: o relógio na tela ao vivo.

### Feat
- Faixa ao vivo com a célula **Relógio**: o tempo conta no navegador entre um poll e outro, sem pular (o servidor manda a fração do segundo) e, em 00:00, a tela passa de nível na hora. Pausado em âmbar, último minuto em vermelho; no intervalo, a célula do nível mostra o que vem depois ("A seguir · nível 7").
- Quem tem "Controlar o relógio" pausa e inicia no botão ao lado do tempo e abre o modal **Relógio** clicando nele: ANTERIOR, INICIAR/CONTINUAR/PAUSAR, PRÓXIMO, −5, −1, +1 e +5 min, com o nível e o que vem depois.
- Sugestão de **ENCERRAR REBUYS** acima da mesa quando o relógio chega ao intervalo com os rebuys abertos (só sugere; some quando o intervalo acaba).
- Celular: o relógio entra na frente da barra de baixo (quatro células), e o Admin abre o modal por ela.

### Test
- `Site\RelogioAoVivoTest` (faixa, controles e modal na noite #18, sugestão no intervalo, Operador sem controles, jogo sem estrutura) e a fração do segundo em `Jogo\RelogioTest`.

## 0.7.1

v2, etapa 2: a regra do relógio.

### Feat
- Relógio do torneio com estrutura de blinds (`Services\Jogo\Relogio`), sem cron: o banco guarda o nível, os segundos que faltavam e desde quando corre; o tempo de agora sai da conta e o relógio passa sozinho pelos níveis (mesmo com a tela fechada). No fim do último nível para em 00:00.
- O nível e os blinds da noite seguem o relógio (no intervalo, o último nível de blinds e o nome do intervalo; com ante, "300 / 600 · ante 600"). A tela ao vivo e o dashboard do site gravam quando o nível muda; a eliminação e o resultado guardam o nível de agora.
- Iniciar o jogo deixa o relógio no nível 1, parado, até apertar INICIAR.
- Comandos (`Actions\Jogo\ControlarRelogio`, permissão nova do site "Controlar o relógio", só o Admin no seed): iniciar/continuar, pausar, próximo e anterior nível (com o tempo cheio) e ± minutos no nível atual.
- Com estrutura, o nível não se digita mais na faixa ao vivo (o lápis some); sem estrutura e no cash game, continua como na v1.
- A Poker Night #18 (seed) fica com o relógio parado no nível 6 com 14:32, como no protótipo.

### Test
- `Jogo\RelogioTest` (início, corre, pausa, passa de nível sozinho, intervalo, fim da estrutura, comandos, eliminação, permissão, jogo sem relógio, formato do tempo) e a faixa da noite #18 em `Site\FasesAoVivoTest`.

## 0.7.0

v2, etapa 1: estruturas de blinds.

### Feat
- Estruturas de blinds no painel (`/painel/estruturas`, permissão `estruturas.*`): nome, descrição, ativo e os níveis em ordem (small, big, ante, minutos) com intervalos, que não contam no número do nível. A prévia **Como fica** numera os níveis e mostra quando cada um começa, antes de salvar. Duplicar copia com os níveis; estrutura em uso não se exclui.
- Duas estruturas para começar (seed): Turbo 15 min (o nível 6 é o 100 / 200 da Poker Night #18, que passa a usar ela) e Deepstack 20 min.
- Jogo: aba **Blinds** no cadastro do torneio, com a estrutura e a tabela dos níveis. Sem estrutura, nível e blinds continuam digitados na noite; cash game não tem estrutura. Duplicar, Salvar como modelo e Criar a partir do modelo levam a estrutura.
- Conferência (tela 04): "Estrutura de blinds" no Evento e a tabela dos níveis.

### Test
- `Painel\EstruturasBlindsTest`; aba Blinds em `Painel\JogosTest`, tabela na `Site\ConferenciaTest` e a cópia em `Jogo\CopiaDoJogoTest`.

## 0.6.4

Fase 5, etapa 5 (última): segurança, grupos, configurações, simulação do prize pool e saldo dos usuários.

### Feat
- Segurança (`/painel/seguranca`): contas do painel com usuário, senha, ativo, grupos, permissões avulsas e último acesso. Ninguém dá o que não tem: só aparecem grupos e permissões que a conta logada também tem, não se mexe em conta mais forte (Admin não troca a senha do Desenvolvedor) e a própria conta não se exclui nem se desativa.
- Grupos (`/painel/grupos`, em Configurações): grupos do painel e do site com a grade de permissões (no painel, uma linha por cadastro: ver, criar, editar, excluir); grupo com gente não se exclui.
- Configurações (`/painel/configuracoes`, tela 25): padrões de um jogo novo (horário, lugares, mesa final, buy-in, rebuy e add-on com valor e fichas, taxa padrão em %) e as faixas da premiação pela regra do clube, com conferência das faixas e dos percentuais.
- Jogo: simulação do prize pool no cadastro, com o formulário ainda não salvo (jogadores, rebuys e add-ons → arrecadação, taxas, prize pool e prêmios).
- Usuários: coluna de saldo em aberto nos jogos finalizados (ordenável, só com `financeiro.ver`).

### Test
- `Painel\SegurancaTest`, `Painel\ConfiguracoesTest`, simulação e padrões em `Painel\JogosTest` e o saldo em `Painel\UsuariosTest`.

## 0.6.3

Fase 5, etapa 4: relatórios (tela 24).

### Feat
- Relatórios no painel (`/painel/relatorios`): filtro de período (temporadas, este mês, mês passado, últimos 30 dias, este ano, tudo ou personalizado), de jogo e de jogador; cartões de resumo e a tabela na tela.
- Seis relatórios: jogos, entradas, prize pools, premiações (por jogador, do melhor resultado para o pior, com ITM; ou os jogos de um jogador com a posição), aluguéis e arrecadação por mês (taxas, fora da premiação e aluguéis).
- Exportar CSV (no jeito do Excel em português) e Excel (.xlsx, dinheiro como número em R$), para quem tem `relatorios.exportar`.
- Exportar PDF sem instalar nada: abre em outra aba uma página clara para imprimir, com o mesmo relatório e filtros, e já chama o diálogo do navegador (Salvar como PDF). A4 em pé, ou deitado nos relatórios largos.

### Test
- `Painel\RelatoriosTest` (os seis relatórios, períodos, CSV, Excel, a página para imprimir e permissões). A noite de três finalizada virou o atalho `noiteFinalizadaDeTres()` do `MontaNoite`, usado também no `Painel\DashboardTest`.

## 0.6.2

Fase 5, etapa 3: dashboard do painel (tela 16).

### Feat
- Dashboard novo em `/painel` (substitui o padrão do Filament e o widget da conta): mês no subtítulo, botão Novo jogo e grade de 3 colunas, como o protótipo.
- Próximo jogo num cartão de feltro: o em andamento ou o próximo agendado, com data, confirmados, mesas, Editar e Abrir no site.
- Números: jogadores cadastrados (ativos e inativos), jogos no mês e mesas disponíveis (quais estão inativas).
- Financeiro do mês (com `financeiro.ver`): arrecadação, prize pools, aluguéis pagos e valores pendentes, com a lista do que cada jogador deve (ou tem a receber) nos jogos finalizados e dos aluguéis vencidos sem pagar, e quanto o caixa deve.
- Gráficos de barras em CSS (sem Chart.js): arrecadação, prize pool e jogadores dos últimos 8 jogos finalizados e jogos por mês; o último em vermelho, dica com o nome e o valor.
- Próximos: a agenda de hoje em diante, com link para o mês no calendário; jogo em rascunho aparece marcado.

### Fix
- Calendário: a consulta do mês usa `whereDate` (no SQLite a data vem com hora e o último dia ficava de fora).

### Style
- Cores das telas próprias do painel em variáveis `--rh-*` no `:root` e no `.dark`, divididas entre calendário e dashboard.

### Test
- `Painel\DashboardTest`: números do mês, valores pendentes e a lista, jogo em andamento antes do agendado, gráficos, próximos e o grupo Geral sem dinheiro.

## 0.6.1

Fase 5, etapa 2: calendário, eventos e aluguéis (telas 17, 18 e 23).

### Feat
- Calendário no painel (`/painel/calendario`): grade do mês de domingo a sábado com jogos, aluguéis de mesa, reservas e bloqueios nas cores do design system, navegação por mês, Hoje e legenda. Jogo em rascunho aparece tracejado; aluguel cancelado sai da grade.
- Novo evento (tela 18) clicando no dia ou no botão: tipo, cliente ou nome, data, horários (vazio = dia todo, pode passar da meia-noite), mesas, responsável, valor e status do aluguel (o valor sugere o aluguel das mesas) e observações. Clicar num evento edita ou exclui; o jogo abre um resumo com Ver no painel e Abrir no site.
- Aviso de mesa ocupada no formulário do evento ("Mesa 01 já tem Poker Night #19 (20:00) nesse horário."), sem impedir de salvar.
- Aluguéis (tela 23, `/painel/alugueis`): totais por status que seguem o filtro de período, abas por status, filtro de período, Marcar pago, Novo aluguel e Editar com o mesmo formulário do calendário; excluir tira do calendário também.
- Novo jogo no calendário leva ao cadastro do jogo (o jogo entra no calendário sozinho).

### Test
- `Painel\CalendarioTest` (grade, criar pelo dia, aviso de conflito, editar e excluir, resumo do jogo, grupo Geral, regras de conflito e de aluguel) e `Painel\AlugueisTest` (lista, totais, abas, período, Marcar pago, novo, editar e excluir).

## 0.6.0

Início da fase 5 (painel completo): modelos, rankings e temporadas.

### Feat
- Modelos no painel (`/painel/modelos`): torneios modelo com o mesmo formulário do jogo, sem nome nem data (Nome do modelo no lugar). Criar jogo a partir do modelo (nome, data e horário) pelo `CriarDeModelo`.
- Jogo: Duplicar e Salvar como modelo no editar e no ver (também de jogo finalizado). Copiam entradas, opcionais, taxas, percentuais da premiação, mesas com dealer, rankings e regras (`Services\Jogo\CopiaDoJogo`); a cópia nasce em rascunho, sem lista nem caixa.
- Rankings no painel: nome, periodicidade, período, ativo, pontos por posição e bônus de mesa final e participação. Classificação por ranking (pontos, jogos, vitórias, melhor posição) só com os jogos do período; empate desfeito por vitórias, melhor posição e jogos.
- Temporadas em Configurações.
- Ranking com pontos e temporada com jogo não se excluem.

### Test
- `Jogo\CopiaDoJogoTest`, `Painel\ModelosTest` e `Painel\RankingsTest`.

## 0.5.4

Fim da fase 4 (telas 12–14). Portão cumprido: uma cópia agendada da Poker Night #18 jogada inteira pelo site (conferência, 12 confirmados, sorteio, iniciar, 12 buy-ins com o duplo do Rafael, eliminação e volta do Lucas, 6 rebuys, nível e blinds, encerrar rebuys, 4 add-ons, fechar entradas, 9 eliminações, resultado do trio final, fechamento, finalizar e acertos feitos) bate R$ 2.100 / 210 / 1.890 → 945 / 567 / 378.

### Fix
- Resultado: o select de posição dava erro 500 no navegador quando o array de posições ainda estava vazio (o Livewire manda o array inteiro, sem chave).
- Resultado: quem ainda está em jogo vem no topo da tabela (vai ficar do 1º ao Nº), não no fim, abaixo de todos os eliminados.
- Tela ao vivo: o aviso de sucesso (fase, nível) some sozinho depois de alguns segundos, em vez de ficar a noite toda.

### Test
- Resultado com o array inteiro vindo do navegador.

## 0.5.3

### Feat
- Fechamento financeiro (tela 14, `/jogos/{jogo}/fechamento`, Livewire `Jogo\Fechamento`): total investido, prêmios (ou cash-outs), o que fica com a casa e a conferência; por jogador (investiu, premiação, resultado e saldo); acertos (quem paga quem, com o caixa) e "Copiar acertos". Avisa quem ainda falta (posição ou cash-out).
- FINALIZAR com confirmação (somente leitura, acertos e pontos gravados, quem fica com pendência); recarrega a página já finalizada.
- Depois de finalizar, cada acerto se marca como FEITO na forma escolhida (`MarcarAcerto`: vira pagamentos e os saldos fecham); clicar no jogador abre o painel só com o pagamento (sem desfazer nem cash-out).

### Test
- `Site\FechamentoTest` (a noite #18 finalizada pela tela) e o acerto feito em `FinalDaNoiteTest`.

## 0.5.2

### Feat
- Resultado e posições (tela 13, `/jogos/{jogo}/resultado`, Livewire `Jogo\Resultado`): pódio das posições premiadas com quem está nelas e o valor; tabela com posição, jogador (ITM), mesa, investido, prêmio e resultado. Quem caiu já vem com a posição; quem ainda está em jogo ganha um select (posição repetida tira do outro) e GRAVAR RESULTADO (`DefinirResultado`). Cash game: cash-outs do maior lucro para o menor, quem está na mesa pendente; clicar na linha abre o painel do jogador.
- O REGISTRAR RESULTADO da premiação passa a aparecer; jogo finalizado em outro aparelho leva a tela ao vivo para o resultado.

### Test
- `Site\ResultadoTest`.

## 0.5.1

### Feat
- Premiação (tela 12, `/jogos/{jogo}/premiacao`, só torneio): do arrecadado ao prize pool (arrecadação, o que ficou fora da premiação, cada taxa, valor retirado antes da premiação), a distribuição gravada ao fechar as entradas (posição, percentual, barra, valor e quem já está na posição) e a regra de distribuição do clube com a faixa da noite marcada. Abre com as entradas fechadas e depois de finalizar.

### Test
- `Site\PremiacaoTest`.

## 0.5.0

Início da fase 4 (final da noite): as regras do resultado, do fechamento e dos rankings, ainda sem tela.

### Feat
- `Services\Jogo\Acertos`: quem paga quem a partir dos saldos, com o caixa como mais um (recebe taxas e o que ficou fora da premiação, ou paga com o dinheiro que já tem); primeiro combinações exatas de 1, 2 ou 3 devedores, depois o maior paga o maior.
- `Services\Ranking\Pontuacao`: pontos por posição, mesa final e participação em cada ranking ativo do jogo; no cash game a posição é a ordem do lucro.
- `DefinirResultado`: com as entradas fechadas, a posição de quem ainda está em jogo (acordo na mesa final), gravada como eliminações do último para o 2º.
- `FinalizarJogo`: confere posições (torneio) ou cash-outs (cash game), finaliza e grava acertos e pontos.
- Fluxo da noite: premiação, resultado e fechamento abrem com as entradas fechadas (cash game a qualquer hora); o aviso diz "abre depois de fechar as entradas".

### Test
- `AcertosTest` (unidade), `FinalDaNoiteTest` e a noite #18 pelas Actions indo até finalizar (acertos levam os R$ 210 para a casa; pontos 13 / 9 / 7…).

## 0.4.4

Fim da fase 3 (telas 07–11).

### Feat
- Resumo ao vivo (tela 10, `/jogos/{jogo}/resumo`, Livewire `Jogo\Resumo`): jogando, buy-ins, rebuys, add-ons, entradas, arrecadação, taxas e prize pool; pendências com o caixa (jogadores devem, caixa deve, entrou no caixa); tabela de jogadores com mesa, buy-in, rebuys, add-on, fichas, total, prêmio ou cash-out, pago e saldo, do maior total para o menor. Filtros por mesa e "Só pendentes"; clicar na linha abre o painel do jogador (sem mudar de lugar). Atualiza a cada 5 s. O link "Resumo" da tela ao vivo e o passo do fluxo da noite passam a abrir.

### Fix
- Eliminação: quem está sentado sem buy-in não conta para "o último em jogo". Antes, com gente sentada sem buy-in, dava para eliminar o último com buy-in (o campeão); agora o botão nem aparece para ele.

### Test
- `Site\ResumoTest` (a noite #18: números, ordem da tabela, fichas 230.000 e filtros) e o caso do último em jogo.

## 0.4.3

### Feat
- Fases na tela ao vivo (Admin): ENCERRAR REBUYS (modal com o que fecha, o add-on que abre e quem fica eliminado de vez) e FECHAR ENTRADAS (tela 11: o que deixa de valer, arrecadação, cada taxa, prize pool, prêmio de cada posição e aviso de quem está jogando sem buy-in). No tablet e no celular o botão fica acima da mesa.
- Depois de fechar as entradas, a lateral mostra a premiação: posição, quem já está nela e o valor.
- Nível e blinds pela faixa (e pela barra do celular): modal com nível (+1) e blinds; `Actions\Jogo\AtualizarNivel` ("100/200" vira "100 / 200").

### Docs
- `CLAUDE.md`: `assertSeeInOrder` do Livewire depois de `call()` compara com o JSON (acentos escapados).

### Test
- `Jogo\NivelTest` e `Site\FasesAoVivoTest` (a noite #18 fechando as entradas pela tela: R$ 2.100 / 210 / 1.890 → 945 / 567 / 378).

## 0.4.2

### Feat
- Painel do jogador na tela ao vivo (telas 08, 08b e 09, Livewire `Jogo\AcoesJogador`): clicar no assento (ou em quem está sem lugar) abre o drawer com BUY-IN / REBUY / ADD-ON conforme a fase, opcionais "1 por jogador" avulsos, o que ele comprou, a conta com o caixa e o histórico.
  - Modal de entrada: escolhe a variação (buy-in simples / duplo…), marca os opcionais (obrigatórios já vêm marcados) e mostra o novo total.
  - Pagamento (sugere o saldo; jogador pagou ou recebeu; pix, dinheiro, cartão ou outro), eliminação (diz se é provisória ou a posição e o prêmio), cash-out com a prévia do valor e do resultado, desfazer a última ação com motivo.
  - MUDAR DE LUGAR / SENTAR NA MESA: a tela ao vivo entra no modo de mudança e o próximo lugar clicado recebe o jogador (quebra de mesa); dá para tirar do lugar ou cancelar.
  - Operador lança; desfazer e mudar de lugar só aparecem para o Admin. Esc fecha o modal e depois o painel.
- `Dinheiro::lerTexto()` (lê '100', '100,50', '1.234,50') e `paraTexto()`.

### Fix
- Sortear lugares de novo deixava sem mesa quem já estava sentado e caía na mesma mesa (o lugar ficava gravado sem a mesa).

### Test
- `Site\AcoesJogadorTest` e o caso do sorteio em `MesasDoJogoTest`.

## 0.4.1

### Feat
- Tela ao vivo (telas 07 e 07m, `/jogos/{jogo}/ao-vivo`, Livewire `Jogo\AoVivo`): faixa com nível e blinds, fase, jogando, entradas, arrecadação e prize pool; mesa escolhida com o total e as fichas de cada jogador (B/2/3, R, A), quem saiu apagado e quem está sem lugar embaixo; lateral com prize pool, taxas, contagem e últimos registros; legenda e GIRAR MESA. Atualiza a cada 5 s. No tablet a lateral some; no celular a mesa abre em pé e blinds, entradas e prize pool ficam presos embaixo.
- Iniciar jogo e o Abrir de jogo em andamento passam a levar para a tela ao vivo.
- `Services\Jogo\Entradas::porJogador()` (buy-in, rebuys, add-ons, opcionais, fichas e total de cada jogador), `Jogo::fichaDoBuyin()` e a prop `rodapes` da mesa.
- `<x-site.operacao larga>`: conteúdo de ponta a ponta.

### Style
- Na tela ao vivo a mesa cabe na altura da tela, deitada ou em pé (notebook de 768px de altura sem rolar).
- Assento: o valor não quebra em duas linhas; sem espaço (celular, três fichas), as fichas descem.

### Test
- `Site\AoVivoTest`.

## 0.4.0

Início da fase 3 (durante a noite): as regras do caixa e das fases, ainda sem tela.

### Feat
- `RegistrarEntrada`: buy-in (um por jogador), rebuy e add-on conforme a fase, opcionais marcados e obrigatórios junto da entrada, opcional "1 por jogador" sozinho, limite por jogador, recompra no cash game; rebuy traz de volta quem caiu na fase de rebuys. uuid repetido devolve a entrada que já existe.
- `RegistrarPagamento`: jogador pagou ou recebeu, parcial ou total, também com o jogo agendado e depois de finalizar (pendências).
- `RegistrarEliminacao` (provisória nos rebuys, definitiva depois, nunca o último na mesa) e `RegistrarCashOut` (fichas × valor da ficha; registrar de novo corrige).
- `EncerrarRebuys` (quem caiu e não voltou fica fora de vez) e `FecharEntradas` (grava o prêmio de cada posição e as posições de quem já caiu).
- `DesfazerAcao`: cancela a última ação do jogador com motivo; entrada leva os opcionais junto, rebuy desfeito devolve a eliminação, eliminação desfeita refaz as posições.
- `Services\Jogo\Saldos` (investido, prêmio ou cash-out, pago, recebido e saldo em centavos) e `Services\Jogo\Posicoes`.
- `eliminacoes.voltou_com`: o rebuy com que o jogador voltou (a eliminação provisória fica como histórico).
- `Dinheiro::multiplicar()` (fichas × valor da ficha com 4 casas) e `cancelar()` no trait `Cancelavel`.

### Fix
- Eliminado e quem fez cash-out só guardam o lugar: quem senta ali não troca com eles (eles ficam sem lugar), diminuir a mesa tira-os do lugar e tirar a mesa não conta com eles. Quem chega atrasado e senta com o jogo em andamento passa a jogando.

### Docs
- `regras.md`: regras de cada ação no jogador, posições e prêmios ao fechar entradas, saldo no cash game e a coluna `voltou_com`.

### Test
- `EntradasTest`, `PagamentosESaldosTest`, `EliminacoesTest`, `DesfazerAcaoTest`, `CashGameTest` e `NoiteDeReferenciaTest` (a noite #18 inteira pelas Actions: R$ 2.100 / 210 / 1.890 → 945 / 567 / 378), com o trait `MontaNoite`.

## 0.3.4

Fim da fase 2 (telas 01–06).

### Feat
- Mesas (tela 06, `/jogos/{jogo}/mesas`, Livewire `Jogo\Mesas`): lista "sem lugar", clicar no jogador e depois num lugar para mover ou trocar, tirar do lugar, sortear lugares, lugares e dealer por mesa, adicionar e tirar mesa, girar a mesa e Iniciar torneio/jogo com confirmação. Operador só vê.
- `<x-site.poker.mesa>` + `resources/js/site/mesa.js`: assentos por comprimento de arco (dealer embaixo), giro de 90° salvo no aparelho, mesa em pé no celular; posições em variáveis CSS para sobreviver ao redesenho do Livewire. `App\Support\PosicoesDaMesa` faz a mesma conta no servidor.
- Actions `TrocarLugar`, `SortearLugares` (equilibrado), `AjustarMesa`, `AdicionarMesa`, `RemoverMesa` (agenda acompanha) e `IniciarJogo` (torneio na fase de rebuys, sentados jogando), com o trait `ChecaRegras`.
- "Organizar mesas" na tela de jogadores passa a levar para as mesas.

### Fix
- Topbar das telas da noite no celular: o selo do status ficava por cima do avatar. O nome do jogo encolhe com reticências e o Sair fica só no ícone.

### Test
- `MesasDoJogoTest` (Actions), `Site\MesasTest` (tela) e `PosicoesDaMesaTest`.

## 0.3.3

### Feat
- Jogadores confirmados (tela 05, `/jogos/{jogo}/jogadores`, Livewire `Jogo\Jogadores`): cartões dos jogadores ativos, clicar confirma ou tira; busca sem diferença de acento e maiúscula; filtros Todos / Selecionados / Frequentes; lista de confirmados com remover; capacidade das mesas ("sobram N" / "faltam N lugares"); NOVO JOGADOR com cadastro rápido em modal; botão Organizar mesas.
- Actions `ConfirmarJogador`, `DesconfirmarJogador` (só quem não tem lançamento) e `CadastrarJogadorRapido` (não duplica nome nem apelido), com permissão `jogadores.confirmar` / `usuarios.cadastro_rapido` e `lockForUpdate`.
- "Conferido, seguir" na conferência passa a levar para os jogadores.

### Docs
- `regras.md`: seção "Lista da noite". `CLAUDE.md`: marcadores do Livewire nos testes e apelido sorteado pela factory.

### Test
- `ListaDeJogadoresTest` (Actions) e `JogadoresTest` (tela).

## 0.3.2

### Feat
- Conferência da estrutura (tela 04, `/jogos/{jogo}/conferencia`): buy-ins com ficha (B, 2, 3), limite e fichas; rebuy e add-on (ou recompra no cash game) com disponível/não tem; opcionais com compra, destino e obrigatório; evento; financeiro (taxas, premiação pela regra com os confirmados ou manual, prize pool estimado); mesas, capacidade, dealers e floor. Botões "Editar no painel" e "Conferido, seguir".
- Layout das telas da noite `<x-site.operacao>`: topbar com o jogo (nome, status, data), fluxo da noite com etapas feitas (✓), atual e as que ainda não abrem.
- Middleware `jogo.etapa`: etapa que o status não permite volta para a etapa atual (ou para a lista) com aviso; rascunho, cancelado e modelo voltam para a lista.
- O "Abrir" de jogo agendado no dashboard e na lista passa a levar para a conferência.

### Test
- `ConferenciaTest`: estrutura da noite #18 agendada, fluxo da noite, cash game com opcional, jogo em andamento barrado com aviso, jogo fora do site e o Abrir da lista.

## 0.3.1

### Feat
- Dashboard do site (tela 02): jogo ao vivo (prize pool real) ou próximo agendado (prize pool estimado pelos confirmados), com data, confirmados, mesas e buy-in; números (em andamento, próximos, jogadores ativos, média por noite); últimos eventos com arrecadação e campeão; agenda com os próximos eventos; última noite.
- Escolha do jogo (tela 03, `/jogos`): destaque do jogo ao vivo ou de hoje, abas Próximos / Em andamento / Finalizados com contagem, busca pelo nome e paginação no estilo do design system. Rascunho, cancelado e modelo não aparecem no site.
- `EtapaDoJogo` (etapas do fluxo da noite, o que cada status permite e para onde o Abrir leva), `Datas` (datas em pt_BR), `Jogo::noSite()`, `buyinPadrao()` e `campeao()`, `Taxa::resumo()` ("10%", "2 × R$ 215"…).
- Componentes `datebox` e `status-jogo`; link "Jogos" na topbar (no celular, só o ícone).

### Test
- `DashboardTest` e `SelecaoDeJogoTest`; estados `agendado()`, `emAndamento()` e `finalizado()` na factory de jogos.

## 0.3.0

Início da fase 2. A `Calculadora` veio da fase 4 para cá porque o dashboard e a conferência já mostram prize pool e premiação.

### Feat
- `App\Services\Jogo\Calculadora`: arrecadação, premiação bruta (por destino), taxas nos cinco cálculos, prize pool, fora da premiação e prêmios (sobra do arredondamento para o 1º), em centavos. `doJogo()` usa as entradas válidas; `simular()` estima pelo cadastro. Resultado em `ResumoFinanceiro`.
- `Dinheiro::percentual()`: percentual de um valor em centavos, meio centavo para cima.

### Docs
- `regras.md`: detalhes de arredondamento, contagem de jogadores e entradas e simulação.

### Test
- `CalculadoraTest`: noite #18 (R$ 2.100 / 210 / 1.890 → 945 / 567 / 378), entrada cancelada, exemplo do sábado (R$ 4.290 / 3.510 / 2.890), taxas por jogador, por entrada e percentual, sobra para o 1º, premiação manual, simulação, opcionais obrigatórios, cash game e jogador sem buy-in.

## 0.2.3

Fim da fase 1.

### Feat
- Cadastro de jogos no painel (telas 19 e 20, `JogoResource`): modalidade no topo e abas Informações, Dinheiro, Premiação, Rankings e Mesas. Entradas em três tabelas com a linha padrão fixa e o + para variações; opcionais com compra e destino; taxas nos cinco cálculos; premiação pela regra do clube ou manual (soma 100%); mesas com dealer e floor do jogo. Cash game pede valor da ficha e não tem add-on.
- Lista de jogos reais com data, modalidade, status, jogadores e arrecadação; filtros por status e modalidade.
- Botões Agendar e Cancelar jogo, pelas Actions `AgendarJogo` e `CancelarJogo` (transação com `lockForUpdate`), com a exceção `RegraDoJogo` para as mensagens de regra.
- Estrutura do jogo (entradas, opcionais, taxas, premiação, mesas) travada no painel a partir de em andamento; finalizado e cancelado só para ver; excluir só rascunho sem entradas (`JogoPolicy`).
- `EventoDoJogo`: salvar o jogo cria ou atualiza o evento na agenda, com as mesas; cancelado sai da agenda.

### Test
- `JogosTest`: lista com arrecadação e sem modelos, torneio completo (entradas, variação, opcionais, taxas, mesas, evento), premiação manual somando 100%, cash game, agendar e cancelar, agendar sem buy-in, jogo em andamento travado, exclusão e grupo Geral só vendo.

## 0.2.2

### Feat
- `PokerNight18Seeder`: a noite de referência com os jogadores e lugares do protótipo. Mesas 01 e 02 com os dealers Sérgio e Paulo; entradas buy-in R$ 100, duplo R$ 200, triplo R$ 300, rebuy R$ 100 e add-on R$ 50; taxa "Casa" 10%; premiação 50/30/20 pela regra de 11–18 jogadores; evento na agenda. A noite fica em andamento no intervalo, com as 22 entradas (12 buy-ins, um deles duplo, 6 rebuys e 4 add-ons). O `db:seed` só a cria no ambiente local.

### Test
- `PokerNight18Test`: cadastro da noite, 22 entradas, R$ 2.100 / 210 / 1.890, prêmios 945 / 567 / 378, investido de cada jogador e seed que não duplica.

## 0.2.1

### Feat
- Cadastro de usuários no painel (tela 21, `UsuarioResource`): foto, nome, apelido e telefone; dealer, floor e ativo; acesso ao site com e-mail (gravado em minúsculas), senha (em branco mantém a atual) e grupos do site. Lista com busca por nome, apelido, telefone e e-mail, quantidade de jogos, pontos e filtros. Excluir é soft delete, com Restaurar.
- O grupo Geral cadastra jogadores, mas não vê a seção de acesso ao site nem edita.

### Fix
- Mensagens de validação estavam em inglês no site e no painel. Agora em português (`lang/pt_BR/`), e as 39 chaves que faltavam na tradução do Filament ficam em `lang/vendor/`.

### Test
- `UsuariosTest` (busca, cadastro mínimo, operador criado no painel entra no site, validações do acesso, senha mantida ao editar, grupo Geral, excluir e restaurar) e `TraducoesTest`.

## 0.2.0

Início da fase 1 (cadastros do painel).

### Feat
- Cadastro de mesas no painel (tela 22, `MesaResource`): nome único, lugares (2–12), valor do aluguel, ativa e observações; coluna com quantos jogos usaram a mesa. Mesa usada em jogo não se exclui (desative).
- Policies do painel pela permissão `recurso.acao` (`PoliticaDoPainel` + `MesaPolicy`); sem exclusão definitiva pelo painel.
- `App\Support\Dinheiro` (centavos inteiros, decimal do banco, `R$ 1.890` / `+R$ 795` / `−R$ 200`) e diretiva `@brl`.
- Ícones do design system no menu do painel.

### Fix
- Painel e site dividem a sessão: entrar num deles mandava para a página pretendida do outro (ex.: depois de abrir `/`, o login do painel caía no login do site). Agora cada login só volta para página do próprio lado.

### Test
- `MesasTest` (lista, sem permissão, criar, validação, mesa usada não se exclui, quem só vê não altera), `DinheiroTest` e testes da página pretendida nos dois logins.

## 0.1.4

### Feat
- Login do site (tela 01) em `/login`: e-mail e senha, "Lembrar acesso", só `usuarios` com `acessa_site` e ativos; 5 tentativas erradas por e-mail+IP bloqueiam por 1 minuto. `POST /logout` para sair.
- `/` atrás do login: casca do dashboard (tela 02) com a topbar do site (`<x-site.window.topbar>`: marca, avatar e Sair). O conteúdo entra na fase 2.
- Componentes `<x-site.props.field>` (rótulo, ajuda e erro) e `<x-site.props.avatar>` (foto ou iniciais); a marca aceita link.
- Login do painel (tela 15) ganha o atalho "Vai operar a noite? Entrar no site de operação".
- Seed cria a conta `dev@rodrigues.test` do site (grupo Admin), com senha em `SITE_SENHA_INICIAL`.

### Test
- `LoginSiteTest`: entrada, e-mail sem diferença de maiúsculas, lembrar acesso, senha errada, sem acesso/inativo/excluído, bloqueio após 5 tentativas, sair, conta do painel não abre o site.

## 0.1.3

### Feat
- SCSS do design system no site (`resources/scss/site/`): tokens como variáveis CSS, fontes Barlow locais, base, tipografia e um parcial por componente do `bundle.css`, com as regras de tablet e celular do protótipo. Shell do admin, calendário e gráficos ficam para o tema do painel.
- Grades responsivas `rh-grid--2…6` e `rh-grid--side` no lugar dos `grid-template-columns` inline do protótipo.
- 35 ícones do design system em `resources/svg/site/icons/` (set `rh` do blade-icons: `@svg('rh-home')`).
- Layout `<x-site.app>` e marca `<x-site.props.brand>`; Livewire e Alpine num bundle só (`resources/js/site/app.js`).
- `/` provisória com o visual do login e `/vitrine` (só local) com todos os componentes para conferir contra o protótipo.

### Fix
- Assets do Filament em `public/` estavam na 5.10.1 com o pacote na 5.10.3 (notificações do painel não carregavam). Republicados, e o `composer.json` passa a rodar `filament:upgrade` depois de cada update.

### Chore
- Saem do skeleton `welcome.blade.php`, `resources/css/app.css`, `resources/js/app.js`, `bootstrap.js` e o axios; entra o `sass`.

## 0.1.2

### Chore
- PHP 8.5 e Laravel 13 (antes PHP 8.2 e Laravel 12, com segurança só até 24/02/2027): `config.platform.php = 8.5`, Tinker 3, PHPUnit 12.
- `config/cache.php`, `session.php`, `logging.php` e `database.php` alinhados ao skeleton do Laravel 13: cache não desserializa objetos (`serializable_classes => false`), sessão em JSON, `max_files` no log diário.
- Painel usa `PreventRequestForgery` no lugar de `VerifyCsrfToken` (renomeado no Laravel 13).

## 0.1.1

### Feat
- Painel Filament 5 em `/painel` (`PainelPanelProvider`): guard `painel`, contas de `seguranca` (`FilamentUser`; só entra conta ativa), tema escuro com as cores dos tokens, marca ♠ e sidebar de 232 px.
- Login do painel (tela 15) pelo campo Usuário (coluna `login`), "Lembrar acesso" e "Entrar no painel"; grava `ultimo_acesso` sem mexer no `updated_at`.
- Tema `resources/css/filament/painel/theme.css` com Barlow e Barlow Condensed locais (`resources/fonts/barlow/`), sem Google/Bunny Fonts.
- Assets do Filament publicados em `public/css`, `public/js` e `public/fonts` e versionados (deploy por FTP).

### Test
- `PainelTest`: visitante vai para o login, fontes locais, login com registro do último acesso, senha errada, conta inativa, login do site não abre o painel.

### Docs
- Protótipos desktop e mobile em `docs/prototipo/` e arquivos do design system (tokens, `bundle.css`, `bundle.js`, fontes) em `docs/design-system/` como referência visual.

## 0.1.0

### Feat
- Migrations das 28 tabelas da v1 (`usuarios` e `seguranca` no lugar de `users`; spatie com tabelas em português).
- Models com relações, casts e escopos (`Jogo::reais()`/`modelos()`, `Item::deEntrada()`/`opcionais()`, `->validas()` em entradas, pagamentos e eliminações).
- Enums em `app/Enums` com `label()` e `opcoes()` para selects.
- `config/auth.php` com os guards `web` e `painel`; `config/permission.php`.
- Seeders: permissões e grupos, regras de premiação, conta `dev` do painel.
- Factories (`Usuario`, `Seguranca`, `Mesa`, `Jogo`) e `tests/Feature/ModeloDeDadosTest`.

### Docs
- Passo a passo de instalação do skeleton por cima dos arquivos em `docs/deploy.md`.

## 0.0.5

### Docs
- Dinheiro do jogo simplificado em três blocos no cadastro: Entradas (buy-in, rebuy e add-on padrão, com **+** para variações como rebuy duplo), Opcionais (destino na premiação: não vai / vai / 50-50) e Taxas (tiradas da premiação antes de dividir).
- Saem `jogo_fundos`, `jogo_movimentacoes` e a realocação; `jogo_despesas` vira `jogo_taxas`; `entradas` guarda o `destino` em vez de `fundo_id`. 28 tabelas na v1.

## 0.0.4

### Docs
- Grupos iniciais: painel Admin, Desenvolvedor, Geral; site Admin e Operador (só lança entradas, pagamentos e eliminações). Permissões do site separadas entre Operador e Admin.
- Dinheiro do jogo em itens (`jogo_itens`, com extras como opcional e churrasco), fundos (`jogo_fundos`), despesas (`jogo_despesas`) e realocação entre fundos (`jogo_movimentacoes`), só pelo Admin do site. Substitui `jogo_opcoes_entrada` e `jogo_taxas`.
- Rankings: cash game também pode valer; posições pontuadas, FT e participação configuráveis por ranking.

## 0.0.3

### Docs
- Acesso em duas tabelas: `seguranca` (painel, guard `painel`) e `usuarios` (site, guard `web`), com grupos e permissões via `spatie/laravel-permission`.
- Taxas cadastradas no torneio (`jogo_taxas`: fixo, percentual, por unidade, por jogador, por entrada), fora do prize pool.
- Torneios modelo (`jogos.e_modelo`) e ação Criar a partir do modelo.
- Rankings no lugar de campeonatos: vários ao mesmo tempo, pontos por lugar, bônus de mesa final e participação; select múltiplo no torneio.
- Blinds e relógio planejados para a v2 (`estruturas_blinds`, `estrutura_niveis`).

## 0.0.2

### Docs
- Cadastro único de `usuarios` (tipo, nome, apelido, telefone, foto; checkboxes "Pode ser dealer" e "Pode ser floor").
- Torneio e cash game no mesmo modelo (`jogos.modalidade`), fases do torneio (rebuys → intervalo → sem entradas), rebuy depois de eliminação até o intervalo.
- Ações no jogador: entrada, pagamento parcial/total, eliminação, cash-out, trocar lugar, desfazer. Nova tabela `pagamentos` e saldos/pendências.
- Campeonatos com pontos e ranking.
- Sim/não como boolean nativo. Ambiente só local por enquanto; hospedagem do lançamento a definir. Mantido PHP 8.2 + Laravel 12.

## 0.0.1

### Docs
- Planejamento inicial: `CLAUDE.md`, `docs/arquitetura.md`, `docs/regras.md`, `docs/painel.md`, `docs/design-system.md`, `docs/deploy.md`.
