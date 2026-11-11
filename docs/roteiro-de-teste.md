# Roteiro de teste

Para testar o sistema inteiro à mão antes do lançamento, na ordem em que uma noite acontece. Cada passo diz o que fazer e, depois da seta, o que tem de acontecer. Quando algo não bater, anote o passo e o que apareceu.

Os testes automáticos (`php artisan test`) já conferem as regras e as contas. Este roteiro confere o uso: as telas, os aparelhos e se o fluxo faz sentido na mesa.

## Antes de começar

- [ ] Site em `http://rodrigues-home-game.test` e painel em `http://rodrigues-home-game.test/painel`.
- [ ] Contas: no banco limpo, as do clube (`.env`, mesmo usuário no painel e no site). Com os dados de exemplo (`DADOS_DE_EXEMPLO=true`): no painel, `dev` ou `teste` (Admin) e `geral` (grupo Geral); no site, `dev@rodrigues.test` ou `teste@rodrigues.test` (Admin) e `operador@rodrigues.test` (Operador, para o celular). `geral` e `operador@` usam a mesma senha da conta `dev` do lado deles (`PAINEL_SENHA_INICIAL` e `SITE_SENHA_INICIAL` do `.env`) e voltam com `php artisan db:seed --class=ContasDeTesteSeeder`.
- [ ] Separe três janelas ou aparelhos: o **notebook do Admin**, o **celular do Operador** e a **TV** (o link do relógio, sem login; pode ser outra janela do navegador, até anônima).
- [ ] Para abrir no celular de verdade: o `.test` só existe no PC. Uma saída é `php artisan serve --host=0.0.0.0 --port=8000` e abrir `http://<IP do PC>:8000` no celular, na mesma rede (talvez precise liberar a porta no firewall do Windows). Sem isso, use o modo celular do navegador (F12).
- [ ] Quer começar do zero? `php artisan migrate:fresh --seed` **apaga tudo do banco local** e recria só o essencial com as contas do clube (banco limpo). As contas `dev`, `geral` e `operador@` e a Poker Night #18 só voltam com `DADOS_DE_EXEMPLO=true` no `.env`. Faça o backup antes (docs/deploy.md, "Zerar o banco local").

Referência da Poker Night #18: 12 jogadores, 22 entradas, R$ 2.100 arrecadados, casa R$ 210, prize pool R$ 1.890, prêmios 950 / 565 / 375 (arredondados para R$ 5; a diferença vai para o 1º).

## 1. Painel: preparar a noite

**Configurações** (menu "Padrões e premiação")
- [ ] Confira os padrões do jogo (horário, lugares, valores e fichas, taxa da casa) → um jogo novo nasce com eles.
- [ ] Confira as faixas da regra de premiação (no seed: até 10 jogadores 65 / 35; de 11 a 18, 50 / 30 / 20; 19 ou mais, 45 / 27 / 17 / 11). Tente salvar uma faixa somando 90% → recusa.
- [ ] "Arredondar prêmios para R$ 5" ligado → no fechar entradas, do 2º para baixo os prêmios saem em múltiplos de R$ 5 e o 1º fica com a diferença.

**Usuários**
- [ ] Cadastre 3 ou 4 jogadores só com o nome → salva; apelido, telefone e foto são opcionais.
- [ ] Marque um como "Pode ser dealer" e outro como "Pode ser floor" → aparecem nos selects de dealer e floor.
- [ ] Crie uma pessoa com **acesso ao site** (e-mail e senha) no grupo **Operador** → entra no site com os mesmos limites do `operador@rodrigues.test`.

**Mesas**
- [ ] Confira as mesas ativas, a capacidade e o valor do aluguel.

**Blinds** (menu do painel)
- [ ] Abra a Turbo 15 min → a prévia "Como fica" numera os níveis (o intervalo não conta) e mostra quando cada um começa.
- [ ] Crie uma estrutura **Teste rápido**: nível 1 de 2 min, nível 2 de 2 min, intervalo de 1 min, nível 3 de 2 min → serve para ver o relógio trocar de nível sem esperar 15 minutos.
- [ ] Tente excluir uma estrutura usada num jogo → não deixa.
- [ ] **Duplicar** a Turbo (na lista ou no editar) → abre "Turbo 15 min (cópia)" com os mesmos níveis. **Gerar próximos níveis** com 4 e intervalo a cada 2 → a prévia mostra o que vem, os níveis entram no fim da lista (no padrão sugerido) e só ficam depois de salvar.
- [ ] No celular em pé, cada nível é um cartão ("Nível N", small, big, ante e minutos lado a lado); deitado, a tabela com os minutos visíveis. Arraste um nível para trocar de lugar.

**Rankings**
- [ ] Crie um ranking com pontos por posição (decisão em aberto: os valores do clube) e, se quiser, bônus de mesa final e de participação.

**Jogo novo (torneio)**
- [ ] Jogos → Novo jogo, torneio, data de hoje.
- [ ] Aba **Blinds**: escolha a Teste rápido → a tabela dos níveis aparece embaixo.
- [ ] Aba **Dinheiro**: buy-in, rebuy e add-on padrão; com o **+**, um buy-in duplo; um opcional com destino 50/50; a taxa da casa de 10%.
- [ ] Mexa na simulação do prize pool (jogadores, rebuys, add-ons) → a conta muda antes de salvar.
- [ ] Aba **Premiação**: pela regra do clube. Aba **Rankings**: o ranking criado. Aba **Mesas**: duas mesas com dealer.
- [ ] Salve → o jogo aparece no Calendário, em rascunho e tracejado.
- [ ] Desative a linha do buy-in padrão, salve e tente **Agendar** → recusa com o motivo (precisa de um buy-in ativo com valor). Ative de novo e agende → status agendado.
- [ ] **Salvar como modelo** → o modelo aparece em Modelos. **Criar jogo** a partir dele → um jogo novo em rascunho com a mesma estrutura. **Duplicar** um jogo → "(cópia)" em rascunho.

**Pré-torneio (aba Confirmados do jogo agendado)**
- [ ] **Confirmar jogadores** → escolha vários do cadastro. **Jogador novo** → nome, apelido e telefone; já entra na lista.
- [ ] **Pagamento** num confirmado → o valor vem com o buy-in (e os obrigatórios); Pix ou Dinheiro (sem Cartão); "Pago antes" mostra o valor.
- [ ] **Tirar da lista** quem não pagou → sai; quem pagou → recusa (tem lançamento).
- [ ] Com **Fichas para quem pagou antes** no cadastro (aba Blinds): **Pagamento** com a caixa "Pagamento antecipado do buy-in" marcada → a coluna Bônus antecipado mostra "Pagou antes · +20.000". **Pagou antes** na linha marca e desmarca; no site, a lista da noite tem o mesmo botão. No dia, o "Quem senta aqui" mostra a marca, e sentar avisa "entregar 40.000 fichas (20.000 do pagamento antecipado)"; o painel do jogador mostra o bônus nas fichas entregues.
- [ ] Como **Geral**: vê a lista, sem Pagamento nem Confirmar.

**Calendário e aluguéis**
- [ ] Clique num dia e crie um aluguel numa mesa do jogo, no mesmo horário → aviso de que a mesa já está ocupada (só avisa, não impede).
- [ ] Marque o aluguel como pago na tela de Aluguéis.

## 2. Site: antes de começar

- [ ] Login com e-mail errado 5 vezes → bloqueia por 1 minuto.
- [ ] Entre como Admin → o dashboard mostra o próximo jogo com o prize pool estimado.
- [ ] Jogos → aba Próximos → **Abrir** → Conferência com evento, estrutura de blinds, entradas, taxas e premiação.
- [ ] **Jogadores**: confirme os jogadores, tire um da lista e confirme de novo.
- [ ] Cadastro rápido com o nome de alguém que já existe → avisa e não cria. Com um nome novo → cria e já confirma.
- [ ] **Mesas**: clique num lugar vazio → "Quem senta aqui" com os confirmados sem lugar; escolha um → senta e some da lista. Busque alguém do cadastro que não estava na lista → confirma e senta. Cadastre um jogador novo ali mesmo.
- [ ] **Sortear lugares** → modal Jogadores: marque alguns (ou Marcar todos), desmarque uma mesa e SORTEAR → só os marcados sentam, só nas mesas marcadas, equilibradas; o modal diz onde cada um senta. Clique num jogador e num lugar vazio (move) e depois num ocupado (troca). Mude os lugares e o dealer de uma mesa.
- [ ] **Ao vivo, JOGADORES** ("N sem lugar"): marque quem chegou, ponha alguém do cadastro pela busca e cadastre um novo (já ficam marcados), SORTEAR → cada um com mesa, lugar e as fichas a entregar (buy-in, time chip, pagou antes). Na aba Na mesa, marque quem voltou com rebuy no intervalo e sorteie de novo. Quem caiu e não voltou não sorteia (avisa para fazer o rebuy antes).
- [ ] Como **Operador**: sem jogo em andamento, o login cai em "Nenhum torneio em andamento".
- [ ] Na **Conferência**, "Relógio nas outras telas": COPIAR LINK e abra o link na TV (numa janela anônima, sem login) → data, horário, nome e "O relógio começa quando o torneio for iniciado · nível 1: …". Nada de dinheiro.
- [ ] Quando iniciar o torneio, a TV troca sozinha para o relógio (sem recarregar à mão).
- [ ] **Iniciar jogo** com menos de 2 sentados → recusa. Com todos sentados → vai para Ao vivo e **cada sentado já tem o buy-in** (o confirmado que não sentou, não). Quem pagou antes pelo painel aparece quitado; quem pagou e não veio, na aba Confirmados, como "Não veio · devolver".

## 3. Durante a noite (torneio)

**Relógio e TV**
- [ ] Na faixa: "Não iniciado" com o tempo cheio. Aperte ▶ → o tempo começa a contar.
- [ ] Na TV, ligue o som → toca a campainha uma vez para conferir. Ponha em tela cheia → os botões e o cursor somem depois de 3 s sem mexer.
- [ ] Recarregue a TV → pede "Toque na tela para ligar o som"; um toque e volta a tocar.
- [ ] Deixe o nível 1 acabar → a faixa, o celular e a TV passam juntos para o nível 2, e a TV toca.
- [ ] Clique no tempo da faixa → modal do Relógio. Teste PAUSAR (âmbar em todas as telas), +1 min, −1 min, PRÓXIMO e ANTERIOR → a TV acompanha em uns 2 s e toca quando o nível muda.
- [ ] Botão **TV** da tela ao vivo → "Relógio nas outras telas": COPIAR LINK (cole no WhatsApp ou num bloco de notas) e ABRIR. Abra o link num celular em pé, deitado e num tablet → o relógio cabe sem rolar; só o relógio, nada de dinheiro nem jogadores.
- [ ] Botão **RELÓGIO** da tela ao vivo → a tela do relógio: no celular em pé (relógio em cima, botões embaixo) e deitado (relógio à esquerda, botões à direita), sem rolar. PAUSAR, PRÓXIMO, ANTERIOR e ± min → a TV acompanha em uns 2 s.
- [ ] **AVISO** no celular → toca no celular na hora e na TV em até 2 s (com o som da TV ligado), e o relógio da TV pisca "AVISO" por uns segundos. Recarregue a TV → o aviso não toca de novo. Como Operador, a tela do relógio aparece sem os botões e sem o AVISO.
- [ ] GERAR OUTRO LINK (Admin) → a TV que estava com o link antigo mostra "Este link não vale mais"; o novo abre. Como Operador, o botão de gerar não aparece.
- [ ] No celular, como Operador → vê o relógio, sem o ▶ e sem o modal.
- [ ] **Sem internet** (estrutura Teste rápido, relógio correndo): na TV, desligue o Wi-Fi do aparelho → depois de uns 15 s aparece "Sem conexão · o relógio segue contando"; o tempo continua, o nível troca na hora certa (com o som) e o intervalo aparece com "Na volta · nível N". Ligue o Wi-Fi → o aviso some e o relógio acerta com o servidor (se alguém pausou nesse meio tempo, pausa agora).

**Entradas e caixa**
- [ ] Clique num assento → painel do jogador: o buy-in já está feito (sentar é entrar). Para trocar um por buy-in duplo: Desfazer o buy-in e lançar o duplo. Opcional avulso (churrasco) pelo botão do painel.
- [ ] O painel do jogador e o Resumo não mostram fichas somadas por jogador (as fichas ficam só na escolha da entrada, como referência).
- [ ] Clique duas vezes rápido no confirmar de um lançamento → grava uma vez só.
- [ ] REBUY em quem ainda não tem buy-in → recusa ("falta o buy-in").
- [ ] Elimine alguém → fica apagado na mesa, e o painel dele mostra **VOLTAR** (entra como rebuy) → volta a jogar no mesmo lugar. Elimine outro e só volte com ele no intervalo.
- [ ] Tente eliminar o último que está em jogo → recusa.
- [ ] Registre um pagamento parcial e depois o resto → o saldo vai de "deve" para "quitado". Pague a mais → o caixa passa a dever a ele.
- [ ] **Desfazer** (Admin) a última ação de alguém, com motivo → some do caixa e do histórico do jogador (no banco fica gravada como cancelada, com o motivo). No celular do Operador o Desfazer não aparece.
- [ ] "Mudar de lugar" no painel do jogador, troque a mesa no alto da tela e clique num lugar dela → quebra de mesa.
- [ ] **Resumo**: os números batem com a lateral da tela ao vivo; "Só pendentes" e o filtro por mesa funcionam.

**Fases**
- [ ] Deixe o relógio chegar ao intervalo → aviso "Intervalo: hora de abrir o intervalo (add-on, e quem caiu pode voltar)", e a TV mostra os blinds da volta.
- [ ] **ABRIR INTERVALO** → o modal diz o add-on que abre, que o rebuy continua e quem está fora agora. Depois disso: ADD-ON abre (1 por jogador) e quem caiu antes volta pelo **VOLTAR** (entra como rebuy).
- [ ] Deixe o intervalo acabar → aviso "O intervalo acabou: hora de fechar as entradas".
- [ ] Atrasado no intervalo: clique num lugar vazio, cadastre ou escolha o jogador → senta e já entra com o buy-in, e muda o número de jogadores.
- [ ] **FECHAR ENTRADAS** → o modal mostra a conta (arrecadação, cada taxa, prize pool, prêmio de cada posição) e avisa quem está jogando sem buy-in. Depois disso: a lateral mostra a Premiação gravada (a TV só mostra o relógio).
- [ ] Elimine jogadores → cada um ganha a posição na hora. Desfaça uma eliminação → as posições se refazem.

**Correções (Admin, ficam no histórico)**
- [ ] Abra o intervalo e use **Voltar aos rebuys** (lateral) com um motivo → volta para a fase de rebuys; o add-on recusa até abrir de novo.
- [ ] Feche as entradas, elimine alguém e use **Reabrir entradas** com um motivo → as posições somem; quem caiu e não voltou aparece com VOLTAR; feche de novo → as posições voltam com a conta nova.
- [ ] Como Operador: não vê Voltar aos rebuys nem Reabrir entradas.

## 4. Final da noite

- [ ] **Premiação** (tela 12): do arrecadado ao prêmio, com cada taxa.
- [ ] **Resultado** (tela 13): defina a posição de quem ainda está em jogo (acordo na mesa final) e grave.
- [ ] **Fechamento** (tela 14): a conferência mostra diferença R$ 0 ✓; "Por jogador" com o saldo de cada um; a sugestão de acertos ("quem paga quem").
- [ ] "Copiar acertos" e cole num bloco de notas → texto pronto para o grupo.
- [ ] **FINALIZAR** → os acertos ficam gravados com o botão FEITO. Marque um como feito → os saldos dos dois fecham.
- [ ] Registre um pagamento de pendência depois de finalizar → aceita, e os acertos em aberto se refazem (não cobra duas vezes).
- [ ] **Desfaça** esse pagamento no painel do jogador (botão Desfazer na linha), com motivo → o saldo volta e os acertos se refazem.
- [ ] **Desfaça** um FEITO no fechamento, com motivo → o acerto volta a estar em aberto.
- [ ] No fim do Fechamento (e na aba **Histórico** do jogo, no painel): início, fases, correções (com o motivo e quem fez), acertos e o finalizar.
- [ ] A TV (link do relógio) mostra "Fim de jogo", sem prêmios.

**No painel, depois da noite**
- [ ] O jogo abre só para ver.
- [ ] Rankings → classificação com os pontos da noite.
- [ ] Usuários → coluna "saldo em aberto" de quem ficou devendo.
- [ ] Dashboard → "Valores pendentes" com o que ficou em aberto.
- [ ] Relatórios: troque o período e o jogador; exporte CSV e Excel e abra no Excel (valores como número, em R$); "Exportar PDF" abre a página de imprimir.

## 5. Cash game

- [ ] Jogo novo em cash game, com o valor da ficha → a aba Blinds e a Premiação somem.
- [ ] Agende, confirme, sente e inicie → Ao vivo sem fase e com o nível digitado (lápis na faixa).
- [ ] Buy-in e recompras; CASH-OUT com as fichas finais → o valor sai de fichas × valor da ficha. Registre o cash-out de novo → corrige.
- [ ] Fechamento: a soma dos cash-outs bate com o que está nas fichas (ou mostra a diferença). Finalize → o ranking ordena pelo lucro.

## 6. Acessos

- [ ] Como Operador: não vê ABRIR INTERVALO, FECHAR ENTRADAS, Desfazer, Finalizar nem os controles do relógio; consegue lançar entradas, pagamentos e eliminações.
- [ ] No painel, uma conta no grupo **Geral**: vê jogos, agenda, rankings e usuários e cadastra jogadores; não vê financeiro, relatórios nem segurança.
- [ ] Em Segurança, como Admin: não aparece o grupo Desenvolvedor para dar a outra conta, e a conta Desenvolvedor não se edita.
- [ ] O login do painel não entra no site, e o do site não entra no painel.

## 7. Noite de ensaio com a planilha

O teste que mais vale: uma noite de verdade no sistema, com a planilha ao lado.

- [ ] Lance tudo nos dois durante a noite.
- [ ] No fechamento, compare: arrecadação, taxas, prize pool, prêmio de cada posição, saldo de cada jogador e os acertos.
- [ ] Anote o que demorou, o que confundiu, onde faltou um botão e o que ninguém usou.

## Sabido que ainda falta

- **Reabrir um jogo finalizado** (posição errada depois de finalizar): não existe; o que se corrige depois de finalizar são os pagamentos e os acertos.
- **Mudar entradas, opcionais ou taxas com o jogo em andamento.** As regras dizem que o Admin do site pode, até fechar as entradas (permissões `estrutura.editar` e `taxas.editar`), mas essa tela ainda não existe no site, e o painel trava essas abas quando o jogo começa. Hoje, o que estiver errado no cadastro precisa ser acertado antes de iniciar.
- Decisões em aberto (`docs/regras.md`): se o Operador registra eliminação (hoje registra) e os pontos dos rankings.
