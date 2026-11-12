# Changelog

Registro das atualizações do sistema do Rodrigues Home Game, organizadas por:

- Feat: nova funcionalidade;
- Fix: correção de bug;
- Style: estilo de uma tela ou componente;
- Refactor: refatoração;
- Test: testes ou experimentos;
- Docs: documentação;
- Chore: manutenção.

## 0.35.1

No ar em https://leticiaebrendon.com.

### Chore
- **Produção na Hostinger** (plano Business, PHP 8.5), no lugar do site do casamento: o Laravel em `domains/leticiaebrendon.com/laravel` e o `public_html` como link para a pasta `public`. O `.env` de produção reaproveita o banco e o SMTP do site antigo, com uma chave nova. O banco é o local limpo (só as 2 contas do clube).
- `public/.htaccess`: http vai para https no domínio de produção (o local continua em http).

### Docs
- `docs/deploy.md`: como está a produção e o passo a passo de atualizar (a Hostinger desliga `proc_open`, `exec` e `symlink`: composer com `--no-scripts`, `package:discover` à mão e o link do storage pelo shell).

## 0.35.0

Opcionais no painel do jogador, etapa 2: os pagos antes (taxa da pizza).

### Feat
- **Pago antes** no opcional (cadastro do jogo, aba Dinheiro, seção Opcionais): dá para marcar em cada jogador que já pagou, e ele **entra junto do buy-in**, com as fichas, quando o jogador senta (`MarcarOpcionalPagoAntes`, tabela nova `participante_opcionais`).
- **Onde marcar**: no painel, aba Confirmados (ação **Opcionais pagos** na linha, ou as caixas "Opcionais pagos antes" no modal do Pagamento, que lança o pagamento e marca juntos; a coluna "Opcionais pagos antes" mostra quem está marcado); no site, na lista da noite (um botão por opcional, embaixo do nome) e no painel do jogador (Marcar / Desmarcar). Mesma permissão do pagamento antecipado (`pagamentos.antecipado`).
- **Ao sentar**: o buy-in já leva o opcional, e o aviso diz o que entregar, com cada opcional: "Ana sentou na Mesa 1 · Lugar 5: entregar 60.000 fichas (40.000 de Taxa Comida)." O "Quem senta aqui" e o modal Jogadores mostram o selo "Taxa Comida · +40.000"; a Conferência, quantos estão marcados.
- **Já sentado**: marcar lança na hora, junto do buy-in; desmarcar tira do buy-in. O que ele comprou na hora pelo cartão continua. Desfazer o buy-in leva junto, e o buy-in refeito traz de novo. Com as entradas fechadas, não muda mais.
- O aviso de qualquer lançamento passa a dizer as fichas de cada opcional que foi junto ("entregar 40.000 fichas (20.000 de Opcional)").

### Test
- `Jogo\OpcionaisPagosAntesTest`: o buy-in leva os marcados (e o rebuy não), desfazer e refazer, marcar e desmarcar quem já sentou, comprado na hora, entradas fechadas, regras e permissão, lista da noite, Quem senta aqui, modal Jogadores e painel do jogador. `ConfirmadosTest`: a ação e as caixas do Pagamento no painel e o buy-in ao iniciar. `JogosTest`: o Pago antes no cadastro.

## 0.34.0

Opcionais no painel do jogador, etapa 1: comprar na hora.

### Feat
- **Opcionais como cartões** ao lado do BUY-IN, REBUY e ADD-ON no painel do jogador (pizza, churrasco, opcional de fichas…), com o preço ou o porquê de não dar agora ("já feito", "falta o buy-in", "entradas fechadas"). Substituem os botões pequenos do "1 por jogador", que ficavam embaixo.
- **Opcional "junto de cada entrada" comprado sozinho**: como o buy-in nasce ao sentar, sem a escolha dos opcionais, o cartão compra um para o buy-in e de novo depois de cada rebuy ou add-on (um por entrada, contando os marcados junto do rebuy). Comprado sozinho, o DESFAZER tira só ele.
- O aviso diz o que entregar: "João comprou Pizza: entregar 10.000 fichas."
- O obrigatório continua indo sozinho com a entrada e não ganha cartão.

### Docs
- Roteiro de teste: a linha das fichas do painel do jogador estava velha (ele soma as fichas desde a 0.28.0).

### Test
- `EntradasTest`: opcional de entrada sozinho, um por entrada, com rebuy e desfazer. `AcoesJogadorTest`: os cartões (antes e depois do buy-in, "já feito", o rebuy abrindo a vez de novo, o obrigatório sem cartão).

## 0.33.0

Nome com apelido nas listas, menu na topbar e o lugar do dealer na mesa.

### Feat
- **Nome e apelido** no Quem senta aqui e no modal Jogadores: "Ricardo Alves (RICO)" (sem apelido, ou apelido igual ao nome, só o nome). A lista segue em ordem de nome.
- **Menu na topbar** (☰): Jogos, **Acessar o painel** (abre em outra aba) e Sair, com o nome de quem está logado em cima. O link do painel só aparece para quem tem conta lá (as contas do clube têm o mesmo usuário nos dois lados).
- **Lugar do dealer no desenho da mesa**: o trilho entra na mesa onde o dealer fica, como nas mesas de verdade, e o recorte acompanha quando a mesa gira. O dealer sai da volta dos jogadores e fica encaixado no recorte, atrás dos lugares que encostarem, com o botão D virado para o feltro.

### Fix
- **Topbar quebrando** em algumas larguras (a data em duas linhas por cima do Jogos): o nome do jogo ganha reticências, a data fica numa linha só e some abaixo de 900px. A barra tem sempre 60px.
- Rotação da mesa salva no aparelho com um valor que não é 0, 90, 180 ou 270 (apagada pela metade, mexida à mão) volta para a posição padrão em vez de espalhar os lugares.

### Test
- `MenuEMesaTest`: nome com apelido, Quem senta aqui e modal Jogadores com nome e apelido, menu com e sem o link do painel, e o lugar do dealer na mesa.

## 0.32.0

Modal Jogadores: quem chegou e o sorteio dos lugares, antes e durante o torneio.

### Feat
- **Botão JOGADORES na tela ao vivo** (com "26 sem lugar"), no lugar da lista de nomes embaixo da mesa. Abre o modal **Jogadores**: marcar quem chegou (busca pelo nome ou apelido), pôr na lista alguém do cadastro ou cadastrar um jogador novo (já ficam marcados), escolher as mesas (com quantos lugares livres cada uma tem) e **SORTEAR**. Depois, o modal mostra onde cada um senta e, no torneio, quantas fichas entregar ("Mesa 2 · Lugar 4 · Entregar 40.000 fichas (20.000 do pagamento antecipado)"). Para um lugar certo, continua o clique no lugar vazio.
- **Sortear quem quiser, onde quiser** (`SortearLugares`): só os marcados e só nas mesas marcadas, antes e durante o jogo. As mesas ficam equilibradas contando quem já está sentado, o lugar de quem caiu conta como livre, e quem senta com o torneio em andamento entra com o buy-in (time chip e pagou antes inclusos). Na aba **Na mesa**, dá para marcar quem já está sentado e sortear de novo (quem voltou com rebuy no intervalo, quebra de mesa). Quem caiu e não voltou não entra no sorteio: primeiro o rebuy.
- Tela 06: **Sortear lugares** abre o mesmo modal (com Marcar todos para o sorteio de todo mundo).

### Test
- `SorteioTest`: quem chegou senta nas mesas escolhidas com o buy-in (e o bônus), mesas equilibradas contando quem fica, quem já estava sentado troca sem novo buy-in, lugar de quem caiu livre, regras (caiu, ninguém, nenhuma mesa, falta lugar), entradas fechadas (tudo ou nada; quebra de mesa vale), Operador não sorteia, o modal inteiro e o botão na tela ao vivo.

## 0.31.0

Excluir qualquer jogo pelo painel, página que não existe volta para o início e o site para de cair no login.

### Feat
- **Excluir com lançamentos**: permissão nova `jogos.excluir_tudo` ("Jogos · excluir com lançamentos (apaga tudo)"), só no grupo Desenvolvedor. Com ela, o Excluir aparece em qualquer jogo, também em andamento e finalizado, na lista, na edição e no Visualizar, e apaga o jogo com a lista, as entradas, os pagamentos, as eliminações, os acertos, os pontos dos rankings, o histórico e o evento da agenda (`ExcluirJogo`). O modal diz o que vai junto: "Apaga o jogo e tudo o que foi lançado nele: 30 jogadores e 2 entradas (R$ 200). Não dá para desfazer."
- Sem a permissão, nada muda: só se exclui jogo em rascunho e sem entradas; o resto se cancela.
- **Sem tela de 404**: página que não existe (jogo excluído, link antigo, endereço digitado errado) volta para a home do site com o aviso "Esse jogo não existe mais." (ou "Essa página não existe."), e no painel, para o Painel de controle com a mesma notificação (`PaginaQueNaoExiste`). A tela ao vivo ou o resumo abertos quando o jogo é excluído recarregam sozinhos no próximo poll e caem na home. Arquivos, JSON e o link do relógio continuam como estavam.

### Fix
- **Site caindo no login do nada** (ex.: selecionar um jogador e, no clique seguinte, ir para o login): no Laragon o PHP roda dentro do Apache, que divide o ambiente entre os sites, e o `.env` de outro projeto aberto ao mesmo tempo vazava para os pedidos daqui (outro banco e outra chave: a sessão sumia). O `.env` agora fica só em cada pedido no Apache (`Env::disablePutenv()` no `bootstrap/app.php`). Conferido com um pedido "poluidor" ao mesmo tempo: antes, 19 de 20 pedidos davam erro; depois, 100 de 100 certos.

### Test
- `JogosTest`: Desenvolvedor exclui a Poker Night #18 em andamento (some com lista, entradas e evento) e um finalizado pelo Visualizar; o Admin continua sem o Excluir e sem passar pela Action. `PaginaQueNaoExisteTest`: site, painel, sem login, e o que continua no 404. `SegurancaTest`: a permissão nova e só o Desenvolvedor com ela. Os testes de excluir passam também no MySQL.

## 0.30.0

Bônus do pagamento antecipado.

### Feat
- **Fichas para quem pagou antes**: no cadastro do torneio, aba Blinds, a seção **Pagamento antecipado** diz quantas fichas a mais ganha quem pagou o buy-in antes (ex.: 20.000). Substitui o opcional de R$ 0 com fichas.
- **Quem marca decide**: o botão **Pagou antes** na aba Confirmados do painel (e a caixa "Pagamento antecipado do buy-in" no modal do Pagamento, que lança o pagamento e marca juntos), na lista da noite do site e no painel do jogador (Marcar / Desmarcar). Permissão nova `pagamentos.antecipado`, no painel e no site, para Admin e Desenvolvedor. Fica no histórico do jogo.
- **Sentar já soma**: o bônus entra no buy-in de quem está marcado (`entradas.antecipado`). O "Quem senta aqui" mostra "Pagou antes · +20.000", e sentar com o torneio em andamento avisa "Ana sentou na Mesa 1 · Lugar 5, pagou antes: entregar 40.000 fichas (20.000 do pagamento antecipado)". Marcar quem já sentou dá as fichas na hora ("entregar 20.000 fichas a mais").
- As fichas entregues (painel do jogador e Resumo) somam o bônus, o Resumo mostra "Pagou antes" no jogador, e a Conferência mostra o bônus e quantos estão marcados.

### Test
- `PagamentoAntecipadoTest`: bônus no buy-in (e não no rebuy), junto do time chip, marcar depois de sentar e desmarcar, permissão e regras, Quem senta aqui e o aviso, painel do jogador, Resumo e lista da noite. Aba Confirmados com o botão e a caixa do Pagamento (`ConfirmadosTest`) e o campo no cadastro (`JogosTest`).

## 0.29.0

Banco limpo para produção e login pelo nome de usuário.

### Feat
- **Login do site pelo usuário ou pelo e-mail**, num campo só ("Usuário ou e-mail"). Coluna nova `usuarios.login` (sem `@`, gravada em minúsculas: "FulanoDeTal" e "fulanodetal" entram igual). Em Sistema → Usuários do site, o campo **Usuário** e a coluna dele; a conta precisa de usuário ou e-mail (ao menos um).
- O login do painel também não diferencia maiúsculas.
- **Contas do clube pelo usuário**: no `.env`, `"Nome <usuário>"`, com o usuário sendo um nome (`FulanoDeTal`) ou um e-mail. A mesma conta entra assim no site e no painel.
- **Banco limpo**: o `db:seed` cria só o que o sistema precisa (permissões e grupos, regras de premiação, estruturas de blinds) e as contas do clube. As contas dev e de teste e a Poker Night #18 só entram no ambiente local com `DADOS_DE_EXEMPLO=true`. Assim o banco local pode ser o de verdade, e um `db:seed` depois de uma atualização não traz exemplo nenhum.

### Chore
- Banco local zerado (com backup antes, fora do git) e recriado limpo, só com as duas contas do clube do `.env` (um Desenvolvedor e um Admin), no site e no painel. Contas e senhas ficam só no `.env`.
- `phpunit.xml` zera as contas do clube e os dados de exemplo: os testes não dependem do `.env`.

### Test
- `BancoLimpoTest`: seed sem exemplos só com o essencial (e rodar de novo não duplica); com `DADOS_DE_EXEMPLO`, a noite e as contas dev. Login pelo usuário no site (`LoginSiteTest`), Usuários do site com usuário sem e-mail e a regra de ter um dos dois, e contas do clube pelo usuário nos dois lados (`ContasDoClubeTest`).

## 0.28.0

Fichas a entregar e time chip.

### Feat
- **Fichas entregues por jogador**: o painel do jogador ganha o bloco "Fichas entregues" com o total e de onde veio ("buy-in 20.000 · time chip 10.000 · rebuys 20.000 · add-on …"), e o Resumo (tela 10) a coluna **Fichas**. Soma as entradas e opcionais válidos (desfeito sai da conta).
- **Aviso diz quanto entregar**: "Buy-in registrado para Ana: entregar 30.000 fichas (10.000 do time chip)." O "Quem senta aqui" também mostra as fichas do buy-in que entra ao sentar.
- **Time chip** (só torneio): na aba Blinds do cadastro do jogo, quantas fichas a mais e até o fim de qual nível (padrão 1). O buy-in lançado dentro do prazo ganha (pelo relógio, ou pelo nível digitado sem estrutura); rebuy e add-on não. Para dar mais tempo de chegar, aumente os minutos do nível 1 na estrutura. Fica gravado no buy-in (`entradas.time_chip`), então desfazer leva junto e quem chega depois não ganha. Não mexe em dinheiro.
- A Conferência (tela 04) mostra o time chip do torneio. Duplicar, Salvar como modelo e Criar a partir do modelo levam o time chip.

### Test
- `Jogo\TimeChipTest`: prazo pelo relógio (inclusive o intervalo depois do nível) e pelo nível digitado, buy-in com e sem time chip, soma por jogador, desfazer, painel do jogador, Resumo e "Quem senta aqui"; time chip no cadastro do painel.

## 0.27.0

Ícones numa pasta só, Temporadas fora do painel e o menu sem filete.

### Feat
- **Todos os ícones do site e do painel em `resources/svg/icones`**, com nome pela ideia (`editar`, `excluir`, `jogadores`, `fichas`, `chevron-direita`…) e um `LEIAME.md` dizendo onde cada um aparece. Para trocar um ícone, troca o arquivo por outro SVG com o mesmo nome: muda no site e no painel.
- Cada item do menu do painel tem o próprio arquivo (`menu-jogos.svg`, `menu-jogadores.svg`…), e os ícones do próprio Filament (editar, excluir, pesquisar, filtros, colunas, setas, paginação, fechar, avisos, tema, sair…) apontam para a pasta (`App\Support\IconesDoPainel`). Os novos começaram com o mesmo desenho de antes.
- O traço (ou preenchimento) fica dentro de cada SVG e o CSS só dá o tamanho: qualquer ícone baixado (Heroicons, Lucide, Tabler…) funciona como está.

### Style
- Item ativo do menu do painel sem o filete vermelho na borda: só o fundo.
- Ícones novos no menu do painel: Painel de controle (computador), Jogadores (mão com cartas), Mesas (mesa), Blinds (relógio) e Rankings.
- **Avatar em ficha**: quem está logado aparece com as iniciais dentro de uma ficha de poker (`ficha.svg` + `App\Support\AvatarDeFicha`), no topo do site e no menu da conta do painel. No painel, troca o ui-avatars.com (que buscava a imagem fora) por esse SVG local.

### Chore
- **Temporadas** fora do painel por enquanto (sem menu, atalho nem o campo no cadastro do jogo); o resto continua, e volta com `TemporadaResource::NO_PAINEL = true`.

### Test
- `IconesTest`: todo ícone usado no código e todo apelido do Filament tem arquivo na pasta, e cada item do menu tem o seu `menu-*`.

## 0.26.0

Site só para o torneio, etapa 6: a bugadinha do topo, a velocidade e as barras de rolagem.

### Fix
- **O topo não pula mais de lado** quando se clica em algo que abre modal ou quando a página muda de altura (site e painel). Era a barra de rolagem sumindo (o modal do Filament põe `overflow: hidden` no `<html>`) e o conteúdo andando o tamanho dela; agora o espaço da barra fica reservado (`scrollbar-gutter: stable`). Conferido: com o modal aberto, o topo fica no mesmo lugar.
- O texto não pisca mais na troca de página: as fontes mais usadas vêm pré-carregadas (`<x-fontes />`).

### Perf
- **Painel em SPA, com pré-busca**: menu, topo e links (também os atalhos e a agenda do Painel de controle) trocam a tela sem recarregar o painel inteiro, e a página já começa a carregar quando o mouse passa no link. O site e a página de impressão abrem de verdade.
- Medido: o servidor responde em 100 a 250 ms no painel e 70 a 100 ms no site (a primeira visita depois de mudar código recompila as views; em produção o `php artisan optimize` resolve). O que pesava era recarregar a página inteira a cada clique.
- Deploy: `php artisan optimize` e `php artisan filament:optimize` no lugar dos três caches soltos.

### Style
- **Barras de rolagem** finas, nas cores do clube (site) e do tema (painel, claro e escuro).

### Test
- SPA no painel com o site fora dele (`Painel\DashboardTest`) e as fontes pré-carregadas no site e no painel (`IconesTest`).

## 0.25.0

Site só para o torneio, etapa 5: a tela de jogadores.

### Feat
- **Jogadores** fica só com o que é de jogador: saíram a coluna e o filtro de acesso ao site e a dica "dê acesso em Usuários do site" do formulário (o acesso continua em Sistema → Usuários do site).
- A coluna "Na noite" virou duas: **Dealer** e **Floor** ("Sim" ou —).
- "Ativo" virou **Ações**: o check verde vira o X vermelho num clique (e volta), sem abrir nada, ao lado de Editar e Excluir.
- No formulário, saiu o título "Na noite": os checks de dealer, floor e ativo ficam no fim dos dados.

### Test
- `Painel\UsuariosTest`: colunas Dealer e Floor, ativo num clique e o formulário sem acesso ao site.

## 0.24.0

Site só para o torneio, etapa 4: editar o torneio em andamento.

### Feat
- **Admin e Desenvolvedor editam o jogo em andamento** no painel (permissão nova `jogos.andamento`, "Jogos · editar em andamento"): as abas Dinheiro, Premiação e Mesas abrem, com um aviso. Modalidade e blinds continuam travadas (o relógio não muda no meio).
- Ao salvar, o jogo se refaz pelo cadastro (`Actions\Jogo\RefazerPeloCadastro`): as entradas já lançadas passam a valer o valor, as fichas e o percentual da premiação de agora; no cash game o cash-out segue o valor da ficha; com as entradas fechadas, os prêmios e as posições são regravados. O que cada um deve e o prize pool acompanham.
- Fica no histórico como **Cadastro alterado** (correção), com o que mudou, e aparece um aviso depois de salvar. Se o recálculo esbarrar numa regra, nada fica gravado (a edição tem transação).
- Linha de entrada ou opcional com entrada lançada não se apaga (desative); a mesa não fica com menos lugares que o último ocupado.

### Fix
- O valor da ficha do cash game no Painel de controle (e no painel do jogador) sai com as casas certas (`Dinheiro::ficha`, "R$ 0,02"); o `Dinheiro::brl` não aceita 4 casas.

### Refactor
- A gravação dos prêmios das posições saiu do `FecharEntradas` para o `Posicoes::gravarPremios`, usado pelos dois.

### Test
- `Jogo\RefazerPeloCadastroTest` (entradas e saldos, prêmios e posições com as entradas fechadas, cash-out, permissão) e, no `Painel\JogosTest`, a edição pelo painel com e sem a permissão.

## 0.23.0

Site só para o torneio, etapa 3: as mesas do torneio sem o cadastro de mesas.

### Feat
- A mesa do torneio tem **nome próprio** ("Mesa 1", "Mesa 2"…) e não depende do cadastro de mesas, que fica para aluguéis e reservas: o torneio pode ter quantas mesas precisar, mesmo com uma só cadastrada.
- No painel, a aba Mesas do jogo pede nome, dealer e lugares; o **+** já traz "Mesa N" com os lugares por mesa do jogo. No site (montar as mesas), **Adicionar mesa** cria a próxima mesa direto, sem escolher do cadastro.
- Na agenda, o jogo não ocupa mais mesas do cadastro (torneio e aluguel não se cruzam no aviso de mesa ocupada); o resumo do jogo no calendário mostra as mesas do próprio jogo.
- Cadastro de mesas: a coluna "Jogos" virou "Na agenda" (eventos), e a mesa só não se exclui se tiver aluguel, reserva ou bloqueio.

### Chore
- Migração `jogo_mesas`: entra `nome` (as mesas que já existem herdam o nome da mesa cadastrada) e sai `mesa_id`; o evento dos jogos solta as mesas da agenda.

### Test
- Testes ajustados para a mesa com nome; novos: adicionar mesa sem cadastro (Action e tela do site), próximo nome, torneio que não ocupa as mesas do cadastro, mesa com evento que não se exclui.

## 0.22.0

Site só para o torneio, etapa 2: premiação e botões + no cadastro do jogo.

### Feat
- **Premiação manual**: cada campo mostra a posição à esquerda (1º, 2º…), que aparece conforme as posições são adicionadas; sem as setas de arrastar (posições fixas: para mudar, troca o valor); o botão de adicionar virou **+**.
- **Sugestões** pelo número de posições da lista: **Concentrada no 1º** (ex.: 55 / 30 / 15) e **Mais equilibrada** (ex.: 40 / 35 / 25), em múltiplos de 5 e com pelo menos 5% por posição. O clique só preenche os campos, e os botões se refazem ao adicionar ou tirar posição.
- **Soma ao vivo** embaixo dos campos ("Soma 95%: falta 5%"). Fora de 100%, salvar mostra um alerta com OK e o jogo não salva. Posição adicionada e não preenchida não conta e some ao salvar.
- Na aba Mesas, o botão de adicionar também virou **+**.

### Test
- `Painel\JogosTest`: o alerta dos 100% (faltando e sobrando), a posição vazia que some, as sugestões (sempre 100, múltiplos de 5, do maior para o menor, de 1 a 30 posições) e o clique na sugestão pelo formulário.

## 0.21.0

Site só para o torneio, etapa 1: login pela janela do torneio.

### Feat
- **Janela do site** (`App\Support\JanelaDoSite`) para quem não é admin (sem `site.fora_da_noite`): o torneio aparece de 3 h antes do início até 3 h depois do **término previsto**, campo novo do cadastro do jogo (`jogos.horario_fim`; antes do início = dia seguinte; vazio = início + 12 h). Em andamento, fica aberto até finalizar.
- Fora da janela a pessoa **continua logada** (antes saía) e vê "Nenhum torneio agora", com o próximo torneio e a hora em que o site abre; ATUALIZAR leva ao jogo quando abre.
- No painel, **Abrir no site** / **Abrir a noite** (Painel de controle e calendário) e o link do fechamento nas pendências abrem em outra aba; sem login no site, passa pelo login e volta para o jogo.

### Refactor
- Saiu a tela **Minha conta** do site (`/conta`) e o link no avatar da topbar: a senha do site muda no painel, em Usuários do site. O Perfil do painel continua.

### Test
- `Site\AcessoDoSiteTest` refeito para a janela (as contas de horário, antes, durante e depois, finalizado até 3 h depois do término, admin a qualquer hora, login no meio do caminho volta para o jogo).

## 0.20.0

Favicon com o logo do clube.

### Feat
- **Favicon e ícones** no site e no painel (e na página de impressão do relatório), a partir do logo do clube: `favicon.ico` (16, 32 e 48), `icone-192.png` e `apple-touch-icon.png`, no fundo claro do logo com os cantos arredondados, legível em aba clara e escura. Antes era o `favicon.ico` vazio do Laravel.
- `php artisan icones:gerar` refaz os ícones de `resources/images/logo.png` (Imagick, só no local).

### Test
- `IconesTest` (o `.ico` com os três tamanhos, os PNGs nos tamanhos certos, site e painel apontando para eles).

## 0.19.0

Painel em módulos, etapa C: o Painel de controle vira a porta de entrada, com destaques e atalhos.

### Feat
- **Próximo jogo em destaque**: o em andamento ou o próximo agendado, com "em 5 dias", data por extenso e **onde a noite está** (agendado → rebuys → intervalo → entradas fechadas → finalizado). Agendado: confirmados, mesas, buy-in, blinds (ou valor da ficha), prize pool estimado e os rankings em que vale ponto. Em andamento: em jogo, entradas, nível do relógio, mesas, arrecadado e prize pool. Dinheiro só com `financeiro.ver`. Editar e Abrir no site; sem jogo agendado, mostra quantos estão em rascunho.
- **Ranking em destaque**: dos rankings ativos que valem hoje, o que já distribuiu mais pontos, com o líder (troféu, pontos, vitórias e jogos), do 2º ao 5º, link para a classificação e os outros rankings que valem hoje.
- **Atalhos** dos módulos (Cadastros, Agenda, Dashboards e relatórios, Sistema), só com o que a conta abre e na ordem do menu, cada tela com uma linha de contexto ("1 agendado · 2 em rascunho", "24 ativos", "1 de 2 ativas", "2 em aberto"…). Um cadastro novo do painel entra sozinho.
- O caixa do mês (arrecadação, prize pools, aluguéis e valores pendentes) continua, só para quem tem `financeiro.ver`, e a agenda fica ao lado dos atalhos.

### Style
- No celular, os cartões de números do painel (caixa do mês, Métricas) ficam dois a dois, com o valor menor.

### Refactor
- Os gráficos dos últimos jogos (arrecadação, prize pool, jogadores e jogos por mês) foram para o rodapé das **Métricas**.
- Os números soltos de jogadores, jogos no mês e mesas viraram a linha de contexto dos atalhos; o `VisaoGeral` virou `CaixaDoMes` (e `ProximoJogo`).
- A conta da classificação do ranking foi para o model (`Ranking::classificacao`, `ordenarClassificacao`, `valendoHoje`), usada pela tela de classificação e pelo Painel de controle.

### Test
- `Painel\DashboardTest` refeito: a ordem da tela, o próximo jogo (agendado, em andamento e sem jogo), o caixa do mês, o ranking em destaque (o que mais pontuou, o que acabou e o inativo ficam fora, sem pontos, sem ranking), os atalhos do Admin e do Geral e os gráficos nas Métricas.

## 0.18.0

Painel em módulos, etapa B: as métricas do clube.

### Feat
- **Métricas** (Dashboards e relatórios, `/painel/metricas`, `relatorios.ver`): as médias dos jogos finalizados no período (mesmo filtro dos relatórios, padrão Tudo). Cartões com jogos, média de jogadores, média de prize pool, gasto por jogador, média de arrecadação, rebuys e add-ons por jogador e meses com jogo; médias em dinheiro arredondadas para o real.
- Comparações de quanto cada jogador gasta, com "+X% da média" / "−X% da média": **por tipo de evento** (o modelo do torneio), **torneio × cash game** e **pelo valor do buy-in**. Gráficos mês a mês de gasto por jogador e de média de jogadores.
- **Tipo de evento** do jogo (`jogos.modelo_id`): o jogo lembra de qual modelo saiu ao **Criar de modelo** e no **Duplicar**; jogo antigo ou criado do zero escolhe no select da aba Informações.

### Test
- `Painel\MetricasTest` (médias e comparações com 4 noites, o modelo lembrado ao criar e duplicar, a tela com filtro vazio e o Geral sem acesso).

## 0.17.0

Painel em módulos, etapa A: o menu e a separação de Jogadores e Usuários do site.

### Feat
- **Menu em módulos** (`navigationGroups` no provider): **Painel de controle** (o dashboard, no topo) · **Cadastros** (Jogos, Modelos, Jogadores, Mesas, Blinds, Rankings, Temporadas) · **Agenda** (Calendário, Aluguéis) · **Dashboards e relatórios** (Relatórios) · **Sistema** (Padrões e premiação, Usuários do site, Contas do painel, Grupos).
- **Jogadores** (Cadastros, `/painel/jogadores`): o cadastro de pessoas, sem a parte de acesso ao site.
- **Usuários do site** (Sistema, `UsuarioDoSiteResource`): só quem entra no site, com e-mail, senha e grupos do site; **Novo usuário do site**, **Dar acesso a um jogador** e **Tirar acesso** (a pessoa continua em Jogadores e sai do site na hora). Permissão nova do painel `acessos.*` (Admin e Desenvolvedor; o Geral não tem).

### Test
- `Painel\UsuariosDoSiteTest` (novo, dar e tirar acesso, validação, editar sem trocar a senha, Geral sem acesso); os casos de acesso saíram do `Painel\UsuariosTest`.

## 0.16.0

Contas de quem administra o clube e a troca da própria senha.

### Feat
- **Contas do clube** (`config/clube.php`, `ContasDoClubeSeeder`, em qualquer ambiente): `CONTA_DESENVOLVEDOR` (grupo Desenvolvedor no painel e no site) e `CONTAS_ADMIN` (Admin nos dois), no `.env` como `"Nome <email>, …"`, com senha inicial em `CONTA_DESENVOLVEDOR_SENHA` / `CONTAS_ADMIN_SENHA`. Cada pessoa ganha os dois logins, com o e-mail como login também no painel. Rodar o seed de novo não mexe em quem já existe. E-mails e senhas ficam fora do repositório.
- Grupo **Desenvolvedor no site**, com todas as permissões do site (como o do painel), mesmo que o Admin perca alguma em Grupos.
- **Minha conta** no site (`/conta`, pelo avatar da topbar): trocar a própria senha, com a senha atual (pelo menos 8 caracteres, diferente da atual; o "Lembrar acesso" dos outros aparelhos cai).
- **Perfil** no painel (`App\Filament\Auth\Perfil`, menu da conta): nome e troca da senha, com a senha atual.
- O login do painel aceita `@` (pode ser o e-mail).

### Chore
- As contas `dev` (painel) e `dev@rodrigues.test` (site) passam a ser criadas só no ambiente local; em produção entram só as do clube.

### Fix
- O aviso "Sem conexão" (etapa 1) mostrava o botão Fechar, que é só do aviso de toque perdido. Visto no navegador: poll sem rede, toque perdido e a volta.

### Test
- `ContasDoClubeTest` (leitura do `.env`, os dois logins e os grupos, seed de novo sem trocar a senha, painel pelo e-mail com o Perfil, Minha conta no site).

## 0.15.0

Revisão geral, etapa 5: manutenção e otimizações.

### Chore
- Dependências atualizadas dentro das versões (Laravel 13.40, Filament 5.11.1, Symfony 8.1.11 e outras): os 11 avisos de segurança do Symfony sumiram (`composer audit` limpo) e o `npm audit fix` resolveu os 2 críticos do `shell-quote` (só desenvolvimento).
- Traduções pt_BR das três mensagens novas do Filament 5.11 (campo, bloco e quadro que não carregaram).
- Pint no projeto inteiro (`bootstrap/providers.php` e `config/permission.php` eram os que faltavam).
- **Testes em paralelo** com o `brianium/paratest`: `php artisan test --parallel` (e `composer test`) roda a suíte em ~1,5 min, contra ~4,5 min antes.

### Perf
- "Valores pendentes" do dashboard do painel: o saldo de todos os jogadores de jogos finalizados sai do banco numa consulta só (`Participante::scopeComSaldo`), em vez de duas consultas por jogo; o `PendenciasTest` confere que bate com o `Saldos` no torneio e no cash game.
- A `Calculadora` lê as regras de premiação do clube uma vez por instância (os relatórios somam muitos jogos).
- O histórico do painel do jogador carrega os opcionais de cada entrada junto, sem uma consulta por linha.

### Docs
- README próprio do projeto (no lugar do padrão do Laravel), `docs/arquitetura.md` com as pastas e classes de hoje (saíram `Helpers/`, `Jogo\Tv` e o `DestinoItem`) e uma seção de **Backup** no `docs/deploy.md`.

## 0.14.0

Revisão geral, etapa 4: quanto de cada item vai para a premiação, sem centavos no torneio.

### Feat
- **Percentual livre por item** (`percentual_premiacao` em `jogo_itens` e `entradas`, no lugar do `destino` vai / 50-50 / não vai): entradas e opcionais escolhem no painel 100%, 50%, 0% ou **Outro** para digitar (ex.: 75%). O que já existia passou para 100 / 50 / 0. A entrada copia o percentual do item na hora; a Conferência mostra o dos opcionais e o das entradas que não vão 100%.
- **Torneio sem centavos**: a premiação bruta desce para o real (os centavos ficam com a casa), a taxa em % arredonda para o real (meio real para cima) e os prêmios saem em R$ 5 com o arredondamento das Configurações ou em R$ 1 sem ele, com a sobra para o 1º. Prêmio que viraria zero desce para o real. Cash game continua até o centavo (é o que está nas fichas).

### Refactor
- Saiu o enum `DestinoItem`; `Item::textoDaPremiacao()` dá o texto ("75% na premiação"). `Dinheiro::REAL` e `Dinheiro::arredondarParaReal()`.

### Test
- `CalculadoraTest` (percentual livre, soma por percentual, prize pool e taxa em reais, prêmios em reais sem o R$ 5, cash game no centavo) e `Painel\JogosTest` (Outro com 75% no cadastro e de volta no formulário). A Poker Night #18 continua em R$ 2.100 / 210 / 1.890 → 950 / 565 / 375.

## 0.13.0

Revisão geral, etapa 3: corrigir o que foi apertado errado, com registro de quem, o quê e quando.

### Feat
- **Histórico do jogo** (tabela `alteracoes`, model `Alteracao`, enum `TipoAlteracao`, `Services\Jogo\Historico`): início, abrir o intervalo, fechar as entradas, resultado, acerto feito, finalizar, todo desfazer e as correções, com quem fez (site ou painel), quando e o motivo. Aba **Histórico** do jogo no painel (com filtro) e, para quem corrige, no fim do Fechamento.
- **Voltar a fase** (`Actions\Jogo\VoltarFase`, tela ao vivo): **Voltar aos rebuys** (o add-on fecha até abrir o intervalo de novo) e **Reabrir entradas** (buy-in, rebuy e add-on voltam, quem caiu e não voltou pode voltar, posições e prêmios gravados saem e se refazem no próximo FECHAR ENTRADAS). Com motivo.
- **Desfazer pagamento** (`Actions\Jogo\DesfazerPagamento`): qualquer pagamento lançado à mão, também depois de finalizar (botão Desfazer na linha do painel do jogador) e no pré-torneio do painel (aba Confirmados). Depois de finalizar, os acertos em aberto se refazem.
- **Desfazer acerto** (`Actions\Jogo\DesfazerAcerto`, fechamento): FEITO marcado errado volta a estar em aberto; os pagamentos dele ficam cancelados (`pagamentos.acerto_id` liga os dois).
- Permissão nova do site `jogo.corrigir` (só o Admin no seed; dá para passar a outro grupo em Grupos). O cancelamento pelo painel guarda a conta no histórico.

### Test
- `Jogo\CorrecoesTest` (voltar aos rebuys, reabrir entradas e fechar de novo, desfazer pagamento e acerto depois de finalizar, histórico da noite, pelo painel) e as telas (`Site\FasesAoVivoTest`, `Site\FechamentoTest`, `Painel\ConfirmadosTest`).

### Chore
- Pint nos três models que estavam fora do padrão (`Mesa`, `Permissao`, `Seguranca`).

## 0.12.0

Revisão geral, etapa 2: o fluxo da noite do jeito que o clube joga.

### Feat
- **Sentar é entrar** (torneio): quem senta na mesa já tem o buy-in padrão, com os opcionais obrigatórios (`RegistrarEntrada::buyinPadrao`). Sentado antes de começar entra ao **Iniciar**; quem chega com o torneio em andamento, na hora em que senta. Mudar de lugar não lança de novo; com as entradas fechadas ninguém novo senta. Outra variação (buy-in duplo) se troca desfazendo. Cash game continua com o buy-in lançado à mão.
- **Quem senta aqui**: clicar num lugar vazio (telas 06 e 07) abre a escolha de quem senta, com os confirmados sem lugar (quem já sentou some), a busca no cadastro de quem não estava na lista e o jogador novo (trait `Livewire\Jogo\Concerns\EscolheQuemSenta`).
- **ABRIR INTERVALO** no lugar de ENCERRAR REBUYS (`Actions\Jogo\AbrirIntervalo`, coluna `jogos.intervalo_aberto_em`): o add-on abre e o rebuy continua, para quem caiu e quer voltar só no intervalo. As eliminações ficam provisórias até **FECHAR ENTRADAS**, que fecha tudo e deixa de vez quem não voltou, na ordem em que caiu. Com o relógio, a tela sugere abrir o intervalo quando ele chega e fechar as entradas quando ele acaba.
- **VOLTAR** no painel do jogador eliminado: o botão do buy-in vira VOLTAR e lança um rebuy.
- **Pré-torneio no painel** (aba **Confirmados** do jogo, `ConfirmadosRelationManager`): confirmar jogadores, cadastrar um novo (com telefone), tirar da lista e lançar o **pagamento antecipado** em Pix ou Dinheiro, com o valor do buy-in já preenchido. Quem pagou e não veio aparece como "Não veio · devolver R$ X". Permissão nova do painel `pagamentos.registrar`; o pagamento grava `registrado_no_painel_por`.
- As Actions da lista e do pagamento aceitam conta do site ou do painel (`ChecaRegras::permitirNosDois`).

### Style
- Fichas só como referência do cadastro: saíram do painel do jogador (no lugar, a hora da entrada) e da coluna Fichas do Resumo; continuam na escolha da entrada e na Conferência.

### Test
- Sentar é entrar (`Jogo\MesasDoJogoTest`), quem senta pela tela (`Site\MesasTest`, `Site\AoVivoTest`), o VOLTAR no intervalo (`Site\AcoesJogadorTest`), as fases novas (`Jogo\EliminacoesTest`, `Site\FasesAoVivoTest`, `Site\RelogioAoVivoTest`) e o pré-torneio (`Painel\ConfirmadosTest`). A noite de referência pelas Actions continua em R$ 2.100 / 210 / 1.890 com o buy-in entrando ao sentar.

## 0.11.0

Revisão geral, etapa 1: o que podia dar errado numa noite real.

### Feat
- **Sessão de 12 h** parada (`SESSION_LIFETIME=720`); cada toque e o poll renovam a contagem.
- **Operador só com jogo em andamento** (middleware `site.acesso`, também em cada clique do Livewire): sem a permissão nova `site.fora_da_noite` (só o Admin do site tem), o começo do site e os outros jogos levam para a tela ao vivo; acabou o torneio, a sessão acaba e fica a página **Nenhum torneio em andamento** (`/sem-torneio`). O painel no mesmo navegador continua logado.
- **Aviso de conexão nas telas da noite** (`resources/js/site/conexao.js`): poll sem rede mostra "Sem conexão com o servidor" (some quando volta) e um toque que não chegou ao servidor mostra que não foi gravado (fica até fechar). Sessão vencida recarrega a página em vez do aviso em inglês do Livewire.
- Pagamentos e acertos só em **Pix ou Dinheiro** (`FormaPagamento::disponiveis()`; Cartão comentado).

### Fix
- **Conta desativada saía só no próximo login**: desligar `ativo` ou `acessa_site` no painel agora tira a pessoa do site na hora, mesmo com "Lembrar acesso".
- **Acerto pago duas vezes**: depois de finalizar, um pagamento lançado pelo painel do jogador refaz os acertos em aberto, e o FEITO confere os saldos antes de lançar (se não bater, refaz a lista e avisa).
- **TV presa depois de uma queda de rede**: a busca do link do relógio desiste em 6 s e tenta de novo; quando a internet volta (ou a tela acende), busca na hora e acerta pausa, próximo e anterior feitos nesse meio tempo. O relógio continua contando sem rede.

### Test
- `Site\AcessoDoSiteTest` (sessão, Operador sem jogo, Operador na noite, fim do jogo, Admin, conta desativada) e, no `Jogo\FinalDaNoiteTest`, a pendência paga pelo painel, o acerto que não bate e as formas de pagamento.
- Factory `foraDaNoite()` para os testes que navegam sem jogo em andamento.
- Teste instável: a factory de jogo sorteava "Poker Night #18" uma vez em 999 e o seeder da noite de referência não a criava; agora sorteia do 19 em diante.

## 0.10.0

v3, etapa 4: tela do relógio do gerenciador e o AVISO. Com ela, a v3 (o relógio no estilo do Poker Depot) está completa.

### Feat
- **Tela do relógio** `/jogos/{jogo}/relogio` (Livewire `Jogo\ControleDoRelogio`, botão **RELÓGIO** na tela ao vivo): o relógio grande (o mesmo painel do link) e botões grandes de ANTERIOR, PAUSAR/CONTINUAR, PRÓXIMO, −5/−1/+1/+5 min, AVISO e LINK DA TV. Em pé, relógio em cima e botões embaixo; deitado, relógio à esquerda e botões à direita; sem rolar do celular ao PC, com a tela acesa onde o navegador deixa. O Operador vê o relógio sem os botões.
- **AVISO** (`Actions\Jogo\DispararAviso`, `relogio.controlar`): toca um "ding-dong" no aparelho de quem apertou e, em até 2 s, em todas as telas do link (0,7 s no teste), com o cartão do relógio piscando "AVISO" por 6 s. Aviso velho (a tela estava sem internet) ou de antes de abrir a página não toca.
- Sons num módulo só (`resources/js/site/som.js`: campainha da troca de nível e o AVISO) e a tela acesa em `resources/js/site/tela-acesa.js`; magics `$tocar` e `$manterAcesa`.

### Test
- `Site\ControleDoRelogioTest` (Admin controla e avisa, Operador só vê, aviso só em andamento, sem estrutura, botão na tela ao vivo, aviso para o navegador). No navegador: a tela em seis tamanhos e o AVISO do celular para a TV.

## 0.9.0

v3, etapa 3: o link do relógio do torneio, no lugar da tela da TV.

### Feat
- **Link do relógio** `/relogio/{codigo}` (`RelogioPublicoController`): abre **sem login** e só para ver, na TV, no tablet, no celular ou no notebook. Mostra só o relógio: nível, tempo, blinds e ante (no intervalo, os da volta), o que vem depois e o intervalo em tempo e em níveis. Nada de dinheiro, jogadores, entradas ou stack médio. Antes de começar mostra quando começa e o nível 1; depois, "Fim de jogo".
- Cada jogo nasce com um código de 8 letras e números (`jogos.relogio_codigo`, sem os que confundem); cópia e modelo ganham um novo. **GERAR OUTRO LINK** (`Actions\Jogo\TrocarLinkDoRelogio`, `relogio.controlar`) troca o código e o link antigo passa a mostrar "Este link não vale mais".
- O link atualiza sozinho: o motor do relógio busca `/relogio/{codigo}/estado` a cada 2 s, uma rota sem sessão, cookie nem CSRF (a tela pode ficar horas aberta, até sem internet, sem "página expirou"); quando o jogo começa ou acaba, a página recarrega sozinha.
- Botão **TV** da tela ao vivo abre "Relógio nas outras telas" (o link, COPIAR LINK, ABRIR, GERAR OUTRO LINK); a Conferência tem o mesmo bloco, para deixar a TV pronta antes de começar. `/jogos/{jogo}/tv` leva para o link.
- `$copiar` (`resources/js/site/copiar.js`): copia também no http da rede local (o `.test`), onde `navigator.clipboard` não existe; o "Copiar acertos" do fechamento passou a usar.

### Style
- Link do relógio em celular em pé (tempo maior, botões só com ícone), celular deitado (relógio à esquerda, blinds e o que vem depois à direita), tablet, notebook e TV, sem rolar.

### Chore
- Saiu a tela da TV da v2 (Livewire `Jogo\Tv`, com prize pool e premiação) e o CSS do prize pool e da premiação dela.

### Test
- `Site\RelogioPublicoTest` (no lugar do `TvTest`): sem login e sem dinheiro, estado sem cookie, chave igual à da página, link trocado, modal da tela ao vivo (Admin troca, Operador só copia), TV antiga, nível digitado, antes, depois e fora do site.

## 0.8.1

v3, etapa 2: o relógio anda sozinho no navegador, também sem internet.

### Feat
- Motor do relógio no navegador (`resources/js/site/relogio-torneio.js`, Alpine `relogioTorneio`, no lugar do `relogio.js`): recebe no `data-relogio` o pacote `EstadoDoRelogio::paraNavegador()` (o estado de agora e o que mostrar em cada nível) e passa de nível sozinho quando o tempo acaba. Sem internet, segue contando e trocando de nível até o fim da estrutura; quando a rede volta, acerta com o servidor.
- TV: "Sem conexão · o relógio segue contando" depois de 15 s sem notícia do servidor; "Intervalo em" com os níveis que faltam ("Intervalo em 2 níveis").
- Som da TV na troca de nível pelo motor: uma vez só por nível, sem repetir com o atraso da rede.
- `<x-site.poker.painel-relogio>`: o bloco grande do relógio, todo pelo motor (vai servir ao link do torneio e à tela do gerenciador).
- Tela ao vivo: quando o navegador passa de nível, pede ao servidor o resto da tela na hora.

### Test
- `Jogo\RelogioTest`: pacote para o navegador (títulos, volta do intervalo, depois, até o intervalo). `Site\RelogioAoVivoTest` e `Site\TvTest` olham o motor no lugar dos atributos antigos. Testado no navegador com a rede cortada: nível 1 → 2 → intervalo → 3 → fim da estrutura sem internet, e a volta.

## 0.8.0

v3, etapa 1: Blinds no painel. A v3 é o relógio no estilo do Poker Depot (link do torneio só com o relógio, relógio que anda sem internet, AVISO); esta etapa deixa o cadastro dos relógios mais rápido e bom no celular.

### Feat
- **Gerar próximos níveis** nas Blinds: continua a estrutura no padrão dos últimos níveis (dobrando, multiplicando ou somando, sugerido e editável), com os minutos do último nível, um intervalo a cada N níveis e a prévia antes de gerar. Valores de ficha arredondados e o small na metade do big (`App\Support\GeradorDeNiveis`).
- **Duplicar** também na tela de edição; cópias seguidas viram "X (cópia)", "X (cópia 2)"… (`EstruturaBlinds::duplicar()`).
- Menu **Blinds** no lugar de "Estruturas de blinds" (e nas permissões dos grupos).

### Style
- Editor de níveis no celular em pé: cada nível é um cartão de duas linhas ("Nível N", intervalo, arrastar e excluir; small, big, ante e minutos lado a lado). Antes, cada nível ocupava quase uma tela e a página passava de 11 mil pixels.
- Celular deitado e PC: a coluna de minutos não esmaga mais o número; intervalo mostra só nome e minutos; o número do nível aparece ao lado do arrastar.

### Test
- `GeradorDeNiveisTest` (padrões, intervalo, arredondamento, recusas) e `Painel\EstruturasBlindsTest` (gerar pelo modal, padrão escolhido, botão desligado sem blinds, duplicar no editar, menu).

## 0.7.4

Prêmios redondos e contas de teste.

### Feat
- "Arredondar prêmios para R$ 5" nas Configurações (ligado por padrão): do 2º lugar para baixo o prêmio desce para o múltiplo de R$ 5 e a diferença vai para o 1º. Vale para a regra do clube e a premiação manual, ao fechar as entradas. A Poker Night #18 passa a pagar 950 / 565 / 375 (antes 945 / 567 / 378).
- Tela 12 (premiação) avisa quando os prêmios gravados foram arredondados.

### Fix
- TV: depois da primeira atualização, o relógio grande mostrava o tempo do "Intervalo em" (1:28:20 no nível 1). O `relogio.js` não declarava `restante`, `rodando` e `fim`, e dentro do componente da TV o Alpine grava a propriedade não declarada no componente de fora, que os dois relógios dividiam. Agora todo o estado fica declarado.
- TV, "Fim de jogo": a linha justa com `overflow: hidden` cortava a perna das letras dos nomes ("Felipe").
- Avatar: iniciais só de letras e números ("Operador (teste)" virava "O(", agora "OT").

### Chore
- `ContasDeTesteSeeder` (só local): `operador@rodrigues.test` no grupo Operador do site e `geral` no grupo Geral do painel, com a senha da conta `dev` de cada lado.

### Test
- `CalculadoraTest`: arredondamento (prize pool quebrado, redondo, prêmio menor que R$ 5, manual que não soma 100%) e a noite #18 sem arredondar. Testes da noite e do painel com os valores novos.

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
