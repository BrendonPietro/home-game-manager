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
| Fonte | Barlow (local, via tema custom) |
| Tema custom | `resources/css/filament/painel/theme.css` (`php artisan make:filament-theme`), entrada no Vite |

Menu (via `navigationSort`): Dashboard, Calendário, Jogos, Modelos, Rankings, Usuários, Mesas, Aluguéis, Relatórios, Segurança, Configurações. Itens sem permissão `.ver` não aparecem.

## Resources

| Resource | Destaques |
|---|---|
| `JogoResource` | Modalidade (torneio / cash game) no topo. Abas: Informações · Dinheiro (Entradas: buy-in, rebuy e add-on padrão com **+** para variações; Opcionais com destino Não vai / Vai / 50-50; Taxas; simulação do prize pool ao lado) · Premiação (só torneio; valida soma 100%) · Rankings (select múltiplo) · Mesas (dealer por mesa, floor do jogo). Ações: Abrir (site), Duplicar, Salvar como modelo, Ver. Somente leitura quando `finalizado` |

**Jogos, como está (fase 1):**
- Lista só com jogos reais (modelos ficam no `ModeloResource`), com data, modalidade, status, jogadores e arrecadação.
- Entradas em três tabelas (Buy-in, Rebuy, Add-on). A linha padrão de cada uma não se apaga, só se desativa; linha desativada não precisa de valor. Cash game não tem add-on e pede o valor da ficha.
- Opcionais com compra "junto de cada entrada" ou "1 por jogador" e destino Não vai / Vai / 50-50. Taxas com os cinco cálculos (valor fixo, %, valor × quantidade, por jogador, por entrada).
- Premiação: "pela regra do clube" (percentuais definidos ao fechar entradas, pelo número de jogadores) ou manual (percentuais na ordem das posições, somando 100%).
- Mesas: mesa ativa, dealer e lugares; mesa com jogador sentado não sai da lista.
- Entradas, opcionais, taxas, premiação e mesas só se editam em rascunho e agendado; durante o jogo quem mexe é o Admin do site. Finalizado e cancelado abrem só para ver.
- Botões **Agendar** (rascunho → agendado, pela `AgendarJogo`: exige data, um buy-in ativo com valor e premiação manual somando 100%) e **Cancelar jogo** (`CancelarJogo`). Excluir só rascunho sem entradas.
- Salvar cria ou atualiza o evento do jogo na agenda, com as mesas (`Services\Jogo\EventoDoJogo`); cancelado sai da agenda.
- Ficam para depois: simulação do prize pool (com a `Calculadora`, fase 4), Abrir no site (fase 2), Duplicar e Salvar como modelo (fase 5).
| `ModeloResource` | Jogos com `e_modelo`; mesmo formulário, sem data. Ação Criar a partir do modelo (nome, data, horário) |
| `RankingResource` | Nome, periodicidade, período, posições pontuadas (repeater), FT pontua? + pontos, participação pontua? + pontos. Página de classificação |
| `UsuarioResource` | Seções Dados (foto, nome, apelido, telefone), Na noite ("Pode ser dealer", "Pode ser floor", ativo) e Acesso ao site (e-mail em minúsculas, senha, grupos do site), mais observações. A seção de acesso só aparece para quem tem `usuarios.editar`: o grupo Geral cadastra jogadores, mas não dá acesso ao site. Excluir é soft delete (filtro de excluídos + Restaurar). Colunas: jogos, pontos, saldo pendente (este entra na fase 4, com `Saldos`) |
| `SegurancaResource` | Contas do painel: nome, login, senha, ativo, grupos e permissões avulsas |
| `GrupoResource` | Grupos do painel (Admin, Desenvolvedor, Geral) e do site (Admin, Operador), filtro por guard, grade de checkboxes de permissões; atribuição de usuários/contas ao grupo |
| `MesaResource` | Capacidade, valor de aluguel, ativa, observações |
| `EventoResource` | Formulário usado no modal do calendário; campos mudam por tipo |
| `AluguelResource` | Totais por status, ação Marcar pago |
| `TemporadaResource` | Em Configurações |

Cada resource tem policy consultando `recurso.acao`; o Filament esconde menu, botões e ações sem permissão. As policies estendem `App\Policies\PoliticaDoPainel`, que traduz as ações do Filament (`viewAny`/`view` → `ver`, `create` → `criar`, `update` → `editar`, `delete`/`restore` → `excluir`) e nunca permite exclusão definitiva. Restrições extras ficam na policy do model (ex.: `MesaPolicy` não exclui mesa usada em jogo). Booleanos nativos: `Toggle`/`Checkbox` direto.

Convenções dos resources:
- Ícones do menu do set `rh` do design system (`'rh-table'`, `'rh-users'`…), como no AdminShell do protótipo.
- `navigationSort` de 10 em 10 na ordem do menu acima (Jogos 30, Usuários 60, Mesas 70…).
- Dinheiro nas colunas com `Dinheiro::brl()`; nos campos, `numeric` com prefixo `R$`.
- Cadastros pequenos (mesas) são resources simples (`--simple`): uma página, criar e editar em modal.

## Widgets do dashboard

- `ProximoJogo` — data, modalidade, confirmados, mesas, botão editar.
- `NumerosDoMes` — jogadores cadastrados, torneios no mês, mesas disponíveis.
- `Financeiro` — arrecadação, prize pools, aluguéis e valores pendentes (saldos em aberto dos jogadores depois de finalizar).
- 4 `ChartWidget` de barras — jogos por mês, jogadores por evento, arrecadação por evento, prize pool por evento. Barras `felt-300`, último evento em `red-500`.

## Páginas custom

- **Calendário** — grade mensal `rh-cal` em Blade, navegação por mês, resumo ao clicar, "+ Novo evento" em modal. Sem plugin de calendário.
- **Relatórios** — filtros período/torneio/jogador; CSV e Excel via `ExportAction`; PDF via `dompdf`.
- **Configurações** — abas Clube · Regras de torneio · Financeiro · Preferências, gravando em `configuracoes`.

## Deploy do painel

- Subir `vendor/` (Filament + Livewire) e rodar `php artisan filament:assets`.
- O tema custom sai no `npm run build` junto com o site.
