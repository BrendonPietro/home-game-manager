# Roteiro de teste

Para testar o sistema inteiro à mão antes do lançamento, na ordem em que uma noite acontece. Cada passo diz o que fazer e, depois da seta, o que tem de acontecer. Quando algo não bater, anote o passo e o que apareceu.

Os testes automáticos (`php artisan test`) já conferem as regras e as contas. Este roteiro confere o uso: as telas, os aparelhos e se o fluxo faz sentido na mesa.

## Antes de começar

- [ ] Site em `http://rodrigues-home-game.test` e painel em `http://rodrigues-home-game.test/painel`.
- [ ] Contas: no painel, `dev` ou `teste` (Admin) e `geral` (grupo Geral); no site, `dev@rodrigues.test` ou `teste@rodrigues.test` (Admin) e `operador@rodrigues.test` (Operador, para o celular). `geral` e `operador@` usam a mesma senha da conta `dev` do lado deles (`PAINEL_SENHA_INICIAL` e `SITE_SENHA_INICIAL` do `.env`) e voltam com `php artisan db:seed --class=ContasDeTesteSeeder`.
- [ ] Separe três janelas ou aparelhos: o **notebook do Admin**, o **celular do Operador** e a **TV** (pode ser outra janela do navegador).
- [ ] Para abrir no celular de verdade: o `.test` só existe no PC. Uma saída é `php artisan serve --host=0.0.0.0 --port=8000` e abrir `http://<IP do PC>:8000` no celular, na mesma rede (talvez precise liberar a porta no firewall do Windows). Sem isso, use o modo celular do navegador (F12).
- [ ] Quer começar do zero? `php artisan migrate:fresh --seed` recria o banco com a Poker Night #18, mas **apaga tudo do banco local**, inclusive as contas `teste` (as `geral` e `operador@` o seed cria de novo).

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

**Calendário e aluguéis**
- [ ] Clique num dia e crie um aluguel numa mesa do jogo, no mesmo horário → aviso de que a mesa já está ocupada (só avisa, não impede).
- [ ] Marque o aluguel como pago na tela de Aluguéis.

## 2. Site: antes de começar

- [ ] Login com e-mail errado 5 vezes → bloqueia por 1 minuto.
- [ ] Entre como Admin → o dashboard mostra o próximo jogo com o prize pool estimado.
- [ ] Jogos → aba Próximos → **Abrir** → Conferência com evento, estrutura de blinds, entradas, taxas e premiação.
- [ ] **Jogadores**: confirme os jogadores, tire um da lista e confirme de novo.
- [ ] Cadastro rápido com o nome de alguém que já existe → avisa e não cria. Com um nome novo → cria e já confirma.
- [ ] **Mesas**: SORTEAR → as mesas ficam equilibradas. Clique num jogador e num lugar vazio (move) e depois num ocupado (troca). Mude os lugares e o dealer de uma mesa.
- [ ] No celular, como **Operador**: Jogadores confirma; Mesas só mostra, sem sortear nem mexer.
- [ ] Abra a **TV** (`/jogos/<id>/tv`) → data, horário, nome e "N confirmados · o relógio começa quando o jogo for iniciado".
- [ ] **Iniciar jogo** com menos de 2 sentados → recusa. Com todos sentados → vai para Ao vivo.

## 3. Durante a noite (torneio)

**Relógio e TV**
- [ ] Na faixa: "Não iniciado" com o tempo cheio. Aperte ▶ → o tempo começa a contar.
- [ ] Na TV, ligue o som → toca a campainha uma vez para conferir. Ponha em tela cheia → os botões e o cursor somem depois de 3 s sem mexer.
- [ ] Recarregue a TV → pede "Toque na tela para ligar o som"; um toque e volta a tocar.
- [ ] Deixe o nível 1 acabar → a faixa, o celular e a TV passam juntos para o nível 2, e a TV toca.
- [ ] Clique no tempo da faixa → modal do Relógio. Teste PAUSAR (âmbar em todas as telas), +1 min, −1 min, PRÓXIMO e ANTERIOR → a TV acompanha em até 5 s e toca quando o nível muda.
- [ ] No celular, como Operador → vê o relógio, sem o ▶ e sem o modal.

**Entradas e caixa**
- [ ] Clique num assento → painel do jogador. BUY-IN para todos; para um, o buy-in duplo; para outro, com o opcional marcado.
- [ ] Clique duas vezes rápido no confirmar de um lançamento → grava uma vez só.
- [ ] REBUY em quem ainda não tem buy-in → recusa ("falta o buy-in").
- [ ] Elimine alguém → fica apagado na mesa. Faça um REBUY nele → volta a jogar no mesmo lugar.
- [ ] Tente eliminar o último que está em jogo → recusa.
- [ ] Registre um pagamento parcial e depois o resto → o saldo vai de "deve" para "quitado". Pague a mais → o caixa passa a dever a ele.
- [ ] **Desfazer** (Admin) a última ação de alguém, com motivo → some do caixa e do histórico do jogador (no banco fica gravada como cancelada, com o motivo). No celular do Operador o Desfazer não aparece.
- [ ] "Mudar de lugar" no painel do jogador, troque a mesa no alto da tela e clique num lugar dela → quebra de mesa.
- [ ] **Resumo**: os números batem com a lateral da tela ao vivo; "Só pendentes" e o filtro por mesa funcionam.

**Fases**
- [ ] Deixe o relógio chegar ao intervalo → aviso "Intervalo: hora de encerrar os rebuys e abrir o add-on", e a TV mostra os blinds da volta.
- [ ] **ENCERRAR REBUYS** → o modal diz o que fecha, o add-on que abre e quem fica eliminado de vez. Depois disso: REBUY recusa; ADD-ON abre (1 por jogador).
- [ ] Buy-in atrasado no intervalo: cadastro rápido, sentar e BUY-IN → entra e muda o número de jogadores.
- [ ] **FECHAR ENTRADAS** → o modal mostra a conta (arrecadação, cada taxa, prize pool, prêmio de cada posição) e avisa quem está jogando sem buy-in. Depois disso: a lateral e a TV mostram a Premiação gravada.
- [ ] Elimine jogadores → cada um ganha a posição na hora. Desfaça uma eliminação → as posições se refazem.

## 4. Final da noite

- [ ] **Premiação** (tela 12): do arrecadado ao prêmio, com cada taxa.
- [ ] **Resultado** (tela 13): defina a posição de quem ainda está em jogo (acordo na mesa final) e grave.
- [ ] **Fechamento** (tela 14): a conferência mostra diferença R$ 0 ✓; "Por jogador" com o saldo de cada um; a sugestão de acertos ("quem paga quem").
- [ ] "Copiar acertos" e cole num bloco de notas → texto pronto para o grupo.
- [ ] **FINALIZAR** → os acertos ficam gravados com o botão FEITO. Marque um como feito → os saldos dos dois fecham.
- [ ] Registre um pagamento de pendência depois de finalizar → aceita (é a única coisa que ainda se lança).
- [ ] A TV mostra "Fim de jogo" com os premiados.

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

- [ ] Como Operador: não vê ENCERRAR REBUYS, FECHAR ENTRADAS, Desfazer, Finalizar nem os controles do relógio; consegue lançar entradas, pagamentos e eliminações.
- [ ] No painel, uma conta no grupo **Geral**: vê jogos, agenda, rankings e usuários e cadastra jogadores; não vê financeiro, relatórios nem segurança.
- [ ] Em Segurança, como Admin: não aparece o grupo Desenvolvedor para dar a outra conta, e a conta Desenvolvedor não se edita.
- [ ] O login do painel não entra no site, e o do site não entra no painel.

## 7. Noite de ensaio com a planilha

O teste que mais vale: uma noite de verdade no sistema, com a planilha ao lado.

- [ ] Lance tudo nos dois durante a noite.
- [ ] No fechamento, compare: arrecadação, taxas, prize pool, prêmio de cada posição, saldo de cada jogador e os acertos.
- [ ] Anote o que demorou, o que confundiu, onde faltou um botão e o que ninguém usou.

## Sabido que ainda falta

- **Mudar entradas, opcionais ou taxas com o jogo em andamento.** As regras dizem que o Admin do site pode, até fechar as entradas (permissões `estrutura.editar` e `taxas.editar`), mas essa tela ainda não existe no site, e o painel trava essas abas quando o jogo começa. Hoje, o que estiver errado no cadastro precisa ser acertado antes de iniciar.
- Decisões em aberto (`docs/regras.md`): se o Operador registra eliminação (hoje registra) e os pontos dos rankings.
