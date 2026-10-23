# Changelog

Registro das atualizações do sistema do Rodrigues Home Game, organizadas por:

- Feat: nova funcionalidade;
- Fix: correção de bug;
- Style: estilo de uma tela ou componente;
- Refactor: refatoração;
- Test: testes ou experimentos;
- Docs: documentação;
- Chore: manutenção.

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
