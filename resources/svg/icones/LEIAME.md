# Ícones do site e do painel

Todos os ícones do sistema estão nesta pasta. **Para trocar um ícone, substitua o arquivo por outro SVG com o
mesmo nome**: ele muda em todo lugar que usa aquela ideia, no site e no painel. Depois de trocar, rode
`php artisan view:clear` (e, em produção, `php artisan icons:cache` se o cache de ícones estiver ligado).

Regras para o SVG novo:

- `viewBox="0 0 24 24"` (ou outro quadrado): o tamanho na tela vem do CSS.
- Cor sempre `currentColor` (no `stroke` de ícone de traço ou no `fill` de ícone cheio): o ícone pega a cor do texto
  ao lado (vermelho no item ativo do menu, cinza no resto).
- Pode ser de traço ou cheio: o traço e o preenchimento ficam dentro do próprio arquivo, então qualquer SVG baixado
  (Heroicons, Lucide, Tabler, Phosphor…) funciona como está.
- Nome em minúsculas com hífen. Para usar um ícone novo no código: `@svg('rh-nome')` no site, `'rh-nome'` no painel.

## Menu do painel (um arquivo por item)

| Arquivo | Item |
|---|---|
| `menu-painel-de-controle.svg` | Painel de controle |
| `menu-jogos.svg` | Cadastros → Jogos |
| `menu-modelos.svg` | Cadastros → Modelos |
| `menu-jogadores.svg` | Cadastros → Jogadores |
| `menu-mesas.svg` | Cadastros → Mesas |
| `menu-blinds.svg` | Cadastros → Blinds |
| `menu-rankings.svg` | Cadastros → Rankings |
| `menu-temporadas.svg` | Cadastros → Temporadas (fora do menu por enquanto) |
| `menu-calendario.svg` | Agenda → Calendário |
| `menu-alugueis.svg` | Agenda → Aluguéis |
| `menu-metricas.svg` | Dashboards e relatórios → Métricas |
| `menu-relatorios.svg` | Dashboards e relatórios → Relatórios |
| `menu-configuracoes.svg` | Sistema → Padrões e premiação |
| `menu-usuarios-do-site.svg` | Sistema → Usuários do site |
| `menu-contas-do-painel.svg` | Sistema → Contas do painel |
| `menu-grupos.svg` | Sistema → Grupos |

Os atalhos do Painel de controle usam os mesmos arquivos do menu.

## Ações

| Arquivo | Onde aparece |
|---|---|
| `adicionar.svg` | Botões de adicionar (+): novo jogo, posição da premiação, mesa, variação… |
| `editar.svg` | Editar (tabelas e páginas do painel, site) |
| `excluir.svg` | Excluir e apagar linha (tabelas, repeaters, confirmação de excluir) |
| `ver.svg` | Ver, mostrar senha, "Ver regra de distribuição" |
| `esconder-senha.svg` | Esconder a senha no campo |
| `duplicar.svg` | Duplicar (jogo, linha de repeater) |
| `copiar.svg` | Copiar (link do relógio, campos) |
| `baixar.svg` | Exportar e baixar (relatórios CSV, Excel, PDF) |
| `enviar.svg` | Importar e enviar arquivo |
| `desfazer.svg` | Desfazer ação na noite, restaurar excluído |
| `pesquisar.svg` | Busca (topo do painel, tabelas, listas) |
| `filtrar.svg` | Filtros das tabelas e gráficos |
| `colunas.svg` | Escolher colunas da tabela |
| `agrupar.svg` | Agrupar linhas da tabela |
| `reordenar.svg` | Arrastar para reordenar (repeaters e tabelas) |
| `mover.svg` | Mudar de lugar na mesa |
| `mais-opcoes.svg` | Menu de mais ações (⋯) |
| `fechar.svg` | Fechar (modal, aviso, menu), "não" em colunas de sim/não, jogador inativo |
| `check.svg` | Confirmado, "sim" em colunas de sim/não, jogador ativo |
| `sair.svg` | Sair (site e painel) |
| `perfil.svg` | Perfil, no menu da conta do painel |

## Setas

| Arquivo | Onde aparece |
|---|---|
| `chevron-direita.svg` | Links "Calendário", "Classificação", migalhas, próxima página, abrir menu lateral |
| `chevron-esquerda.svg` | Página anterior, recolher menu lateral |
| `chevron-cima.svg` | Recolher grupo do menu, seção e linha; ordem crescente na tabela |
| `chevron-baixo.svg` | Abrir grupo, seletores, ordem decrescente na tabela |
| `chevron-cima-baixo.svg` | Coluna que dá para ordenar |
| `chevron-duplo-esquerda.svg` / `chevron-duplo-direita.svg` | Primeira e última página |
| `seta-direita.svg` | "Abrir no site", ir para a próxima etapa |
| `seta-cima.svg` / `seta-baixo.svg` | Subir e descer linha |

## Avisos e estados

| Arquivo | Onde aparece |
|---|---|
| `alerta.svg` | Avisos (âmbar), confirmação antes de uma ação |
| `erro.svg` | Aviso de erro |
| `info.svg` | Aviso de informação |
| `sucesso.svg` | Aviso de sucesso |
| `vazio.svg` | Tabela ou gráfico sem nada |
| `notificacoes.svg` | Notificações do painel |
| `cadeado.svg` | Fechar entradas, fase travada |

## Torneio e site

| Arquivo | Onde aparece |
|---|---|
| `inicio.svg` | Início do site |
| `painel.svg` | Acessar o painel (menu do topo do site), relatório de arrecadação |
| `jogadores.svg` | Jogadores (etapa da noite, listas, botão JOGADORES da tela ao vivo) |
| `mesa.svg` | Mesa |
| `ficha.svg` | Avatar de quem está logado (topo do site e menu da conta do painel): a ficha com as iniciais no miolo, no lugar dos naipes (`App\Support\AvatarDeFicha`). Outra ficha serve se o desenho for redondo e centrado: o miolo escondido é o centro com 27% da largura |
| `fichas.svg` | Fichas e dinheiro: registrar pagamento, entradas, pré-pagamento |
| `trofeu.svg` | Ranking em destaque, premiação |
| `eliminado.svg` | Eliminação |
| `relogio.svg` | Relógio do torneio |
| `continuar.svg` / `pausar.svg` | Continuar e pausar o relógio |
| `tela-cheia.svg` | Tela cheia no link do relógio |
| `som-ligado.svg` / `som-desligado.svg` | Som do link do relógio |
| `tv.svg` | Link do relógio (TV) |
| `girar.svg` | Girar a mesa, sortear lugares |
| `calendario.svg` | Datas e agenda |
| `grafico.svg` | Gráficos e relatórios |
| `lista.svg` | Jogos (menu do topo do site), classificação |
| `chave.svg` | Aluguéis, acesso |
| `configuracoes.svg` | Configurações |
| `menu.svg` | Abrir o menu do topo do site (Jogos, Acessar o painel, Sair) |
| `tema-claro.svg` / `tema-escuro.svg` / `tema-do-sistema.svg` | Escolha do tema no menu da conta do painel |

A lista com todos os ícones desenhados fica na vitrine do site (`/vitrine`, só no ambiente local).
