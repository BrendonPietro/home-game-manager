# Design system

O design system do clube está publicado no Claude (artifact **Rodrigues Home Game**, tipo Design System), com tokens, 18 componentes e as 25 telas. Há também dois protótipos navegáveis: desktop e mobile.

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

## Breakpoints

- ≤ 1100 px: grades de 5–6 colunas viram 3.
- ≤ 900 px (tablet): layouts com coluna lateral empilham; painel admin ganha menu ☰; coluna lateral da tela ao vivo some (prize pool fica na faixa do topo).
- ≤ 600 px (celular): mesa abre girada 90°; fluxo da noite rola de lado; modais sobem de baixo; calendário mostra eventos como barras.
