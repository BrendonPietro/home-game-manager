# Design system

O design system do clube está publicado no Claude (artifact **Rodrigues Home Game**, tipo Design System), com tokens, 18 componentes e as 25 telas. Há também dois protótipos navegáveis, desktop e mobile, copiados para o repositório:

- `docs/prototipo/rodrigues-prototipo.html` e `docs/prototipo/rodrigues-mobile.html` — referência visual de todas as telas (ver `docs/prototipo/README.md`).
- `docs/design-system/` — `tokens.json`, `bundle.css` (classes `rh-`), `bundle.js` (inclui `RHG.pokerTable`, referência para `mesa.js`) e as fontes Barlow / Barlow Condensed em `.woff2` (copiar para `resources/fonts/barlow/`).

Visual vem dos protótipos; regras vêm de `docs/regras.md` (onde divergirem, vale o `regras.md`).

## Direção

Escuro por padrão, grafite + feltro verde discreto + vermelho carta como acento. Fichas como única referência de cassino recorrente. Números de dinheiro com mais destaque que qualquer outro texto.

## Tokens principais

| Token | Valor | Uso |
|---|---|---|
| `bg-000` / `bg-100` / `bg-200` / `bg-300` / `bg-400` | `#0b0c0d` / `#121415` / `#181b1d` / `#202427` / `#2a2f33` | Página → cromo → painéis → hover → selecionado |
| `ink-100` / `ink-200` / `ink-300` | `#eeeae3` / `#b4aea5` / `#938d85` | Texto principal / secundário / rótulos |
| `red-500` | `#c62f3a` | Acento: um primário por área, AO VIVO, torneio no calendário |
| `red-300` | `#f07a7f` | Valores negativos (sempre com −) |
| `green-300` | `#5fcf8f` | Valores positivos (sempre com +), pago |
| `felt-500` / `felt-700` / `felt-900` | `#1b5940` / `#13402f` / `#0d261c` | Mesa, próximo torneio, prize pool |
| `chip-buyin` / `chip-rebuy` / `chip-addon` | `#e8e4dc` / `#5b9be8` / `#e8ad44` | Fichas B / R / A |
| Fontes | Barlow Condensed (display, números), Barlow (texto) | Arquivos `.woff2` locais |
| Raios | 3 / 5 / 8 / 999 px | Badge / botão e painel / assento / ficha e mesa |

## Do protótipo para o Laravel

| No design system | No projeto |
|---|---|
| `tokens.json` | `resources/scss/site/vars/_tokens.scss` |
| `bundle.css` (classes `rh-`) | Um parcial por componente em `resources/scss/site/components/` |
| Camada responsiva | `resources/scss/site/core/_responsive.scss` com classes de grade (`rh-grid--4`, `rh-grid--side`) no lugar dos estilos inline |
| `RHG.icon()` | SVGs em `resources/svg/site/icons/` + `@svg('rh-…')` |
| `RHG.brl()` | `Dinheiro::brl()` + diretiva `@brl()` |
| `RHG.pokerTable()` | `<x-site.poker.mesa>` + `resources/js/site/mesa.js` (posição por comprimento de arco, rotação 90°) |
| `siteShell` | Layout `<x-site.operacao>`: topbar + fluxo da noite + faixa ao vivo |
| `adminShell` | Layout do Filament com o tema custom |

### SCSS do site

`resources/scss/site/index.scss` usa `vars` (tokens e breakpoints), `core` (fontes, base, tipografia, layout, grades), `components` (um parcial por bloco do `bundle.css`) e `pages` (layout próprio de cada tela).

- As variáveis CSS têm os mesmos nomes do `bundle.css` (`--bg-000`, `--font-display`, `--radius-md`…), então uma classe copiada do protótipo funciona sem mudar nada.
- As regras responsivas que no protótipo ficam em `.rh-screen` entram em cada componente (`@include ate(celular) { … }`).
- Diferenças em relação ao `bundle.css`:
  - `.rh-scrim` e `.rh-drawer` são `fixed` na janela, não `absolute` dentro da tela.
  - Acréscimos: `.rh-field__error`, `.rh-input[aria-invalid]`, foto no `.rh-avatar` e `.rh-strip > .is-prize` (destaque do prize pool, que no protótipo é um estilo inline).
- Ficam **fora** do site, porque só aparecem no painel: shell do admin (`rh-app`, `rh-side`, `rh-nav`, `rh-pagehd`, `rh-content`, `rh-mtop`), calendário (`rh-cal`, `rh-ev`, `rh-legend`, `rh-dot`) e gráficos (`rh-bars`, `rh-tip`). Quando o calendário (tela 17) e os gráficos (tela 16) forem feitos, os estilos entram no tema do Filament.
- Conferência: o CSS gerado tem as mesmas declarações do `bundle.css` para todos os seletores portados.

### Grades

No protótipo as telas usam `grid-template-columns` inline. Aqui viram classes:

| Classe | Desktop | ≤ 1100 | ≤ 900 | ≤ 600 |
|---|---|---|---|---|
| `rh-grid--2` | 2 | 2 | 2 | 1 |
| `rh-grid--3` | 3 | 3 | 2 | 1 |
| `rh-grid--4` | 4 | 4 | 2 | 2 |
| `rh-grid--5` / `--6` | 5 / 6 | 3 | 2 | 2 |
| `rh-grid--side` | conteúdo + lateral (`--side`, padrão 360px) | = | 1 | 1 |

Layouts que não cabem nessas grades (ex.: `1.6fr 1fr 1fr 1fr`) ficam no parcial da tela em `pages/`.

### Mesa

`<x-site.poker.mesa>` segue o `RHG.pokerTable`: assento 0 é o dealer, embaixo; os lugares vêm em sentido horário, espaçados pelo comprimento de arco da oval (em proporção real). Diferenças:

- A posição de cada assento é uma variável CSS no contêiner (`left:var(--x3);top:var(--y3)`). O servidor escreve a posição de partida (`App\Support\PosicoesDaMesa`) e o `mesa.js` reescreve ao girar e ao mudar o tamanho da tela. Assim o Livewire pode redesenhar os assentos sem que eles pulem.
- Em pé (90°/270°) a mesa ganha `.is-retrato` e no máximo 520px de largura. Antes do primeiro desenho do JS os assentos não animam (`.is-pronta`).
- A busca do ponto na oval começa na amostra 0 (no protótipo começava na 1, deixando o dealer 0,37% fora do centro).
- Tela 06 usa a mesa compacta com `ar` 2,6 deitada e 0,72 em pé, raios 44/36, como o protótipo.
- Tela ao vivo usa a mesa padrão (até 960px). Cada assento mostra o total investido e as fichas (prop `rodapes`): buy-in B (ou 2, 3 na variação que vale 2×, 3× o padrão), rebuy R (ou a quantidade, a partir de 2) e add-on A. Eliminado e quem fez cash-out ficam apagados (`rh-seat--out`).

### Tela ao vivo

`pages/_ao-vivo.scss` segue o `LIVE_CSS` do protótipo (palco + lateral de 330px). Diferenças:

- A faixa troca o **Relógio** (v2) pela **Fase** do torneio (Rebuys abertos, Intervalo, Entradas fechadas); no cash game não tem fase e o nível vira só "Blinds". "Jogadores" virou **Jogando** (quem ainda está na mesa).
- A lateral mostra também o que fica fora da premiação ("Arrecadado · taxas · fora"); no cash game a contagem é Buy-ins, Recompras e Cash-outs.
- Quem está sem lugar (chegou atrasado ou saiu da mesa na quebra) aparece embaixo da mesa.
- O botão de girar fica fora da mesa ("GIRAR MESA") e não mostra os graus.
- Painel do jogador (telas 08, 08b e 09, `pages/_jogador.scss`): drawer com BUY-IN / REBUY / ADD-ON (`.rh-action`, desabilitado com o motivo embaixo: "já feito", "no intervalo", "falta o buy-in"), o que ele comprou, a conta com o caixa (investido, prêmio ou cash-out, pago, recebido e "Deve ao caixa" / "Caixa deve a ele" / "Quitado") no lugar do "Total investido" do protótipo, e o histórico com entradas, pagamentos e eliminações (Desfazer só no último). Um modal só serve buy-in (08b), rebuy e add-on (09): escolhe a variação (`.rh-pick`) e marca os opcionais. Também há modais de pagamento, eliminação, cash-out e desfazer (com motivo). O aviso de sucesso aparece no topo do drawer (`.rh-warn--ok`), não como toast.
- Scrims: `.rh-scrim--leve` atrás do drawer (a mesa continua à vista) e `.rh-scrim--topo` para o modal por cima do drawer.
- Fases (Admin): ENCERRAR REBUYS e depois FECHAR ENTRADAS no pé da lateral, como no protótipo; no tablet e no celular (sem lateral), numa linha da barra acima da mesa. Cada um abre um modal: Encerrar rebuys diz o que fecha, o add-on que abre e quem fica eliminado de vez; Fechar entradas é a tela 11 (o que deixa de valer, arrecadação, cada taxa, prize pool, prêmio de cada posição e quem está jogando sem buy-in). Com as entradas fechadas, a lateral ganha a Premiação (posição, quem já está nela e o valor).
- Nível e blinds: a célula da faixa (e a de Blinds na barra do celular) vira botão com lápis para o Admin e abre um modal com nível (+1) e blinds.
- Resumo ao vivo (tela 10, `pages/_resumo.scss`): os 8 números do protótipo (Jogadores virou **Jogando**; a taxa única aparece com o nome, "Casa 10%") e, abaixo, as pendências com o caixa (jogadores devem, caixa deve, entrou no caixa). A tabela não tem a coluna "Buy-ins" (é sempre 1) e ganhou Prêmio/Cash-out (quando há), Pago e Saldo ("deve R$ 40" em vermelho, "recebe R$ 50" em verde, "quitado"); "Só pendentes" e as mesas filtram; clicar na linha abre o painel do jogador. No celular a tabela rola de lado dentro do painel. O botão Exportar do protótipo fica para os relatórios (fase 5).
- Mudar de lugar na tela ao vivo: faixa vermelha acima da mesa ("João: clique num lugar vazio…") com Tirar do lugar e Cancelar.
- A mesa cabe na altura da tela: a largura máxima é a altura que sobra (100svh − 252px) × a proporção (`--ar`), até 960px deitada e 520px em pé. No protótipo a mesa em pé passava da tela num notebook.

### Vitrine

`/vitrine` (só com `APP_ENV=local`) mostra todos os componentes com os dados da Poker Night #18, para comparar com os protótipos.

## Breakpoints

- ≤ 1100 px: grades de 5–6 colunas viram 3.
- ≤ 900 px (tablet): layouts com coluna lateral empilham; painel admin ganha menu ☰; coluna lateral da tela ao vivo some (prize pool fica na faixa do topo).
- ≤ 600 px (celular): mesa abre girada 90°; fluxo da noite rola de lado; modais sobem de baixo; calendário mostra eventos como barras.
