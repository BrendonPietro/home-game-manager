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
| `ModeloResource` | Jogos com `e_modelo`; mesmo formulário, sem data. Ação Criar a partir do modelo (nome, data, horário) |
| `RankingResource` | Nome, periodicidade, período, posições pontuadas (repeater), FT pontua? + pontos, participação pontua? + pontos. Página de classificação |
| `UsuarioResource` | Grupo, nome, apelido, telefone, foto, acesso ao site (e-mail/senha), "Pode ser dealer", "Pode ser floor", ativo, observações. Colunas: jogos, pontos, saldo pendente |
| `SegurancaResource` | Contas do painel: nome, login, senha, ativo, grupos e permissões avulsas |
| `GrupoResource` | Grupos do painel (Admin, Desenvolvedor, Geral) e do site (Admin, Operador), filtro por guard, grade de checkboxes de permissões; atribuição de usuários/contas ao grupo |
| `MesaResource` | Capacidade, valor de aluguel, ativa, observações |
| `EventoResource` | Formulário usado no modal do calendário; campos mudam por tipo |
| `AluguelResource` | Totais por status, ação Marcar pago |
| `TemporadaResource` | Em Configurações |

Cada resource tem policy consultando `recurso.acao`; o Filament esconde menu, botões e ações sem permissão. Booleanos nativos: `Toggle`/`Checkbox` direto.

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
