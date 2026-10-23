# Protótipos

Referência **visual** do site e do painel. Abra no navegador (precisa de internet só para as fontes do Google; no projeto as fontes são locais).

| Arquivo | O que é |
|---|---|
| `rodrigues-prototipo.html` | Desktop: as 25 telas navegáveis (site 01–14, painel 15–25) |
| `rodrigues-mobile.html` | Mesmas telas pensadas para tablet e celular (breakpoints em `docs/design-system.md`) |

## Como usar no desenvolvimento

- **Visual vem daqui:** layout, espaçamentos, cores, tipografia, componentes, estados (vazio, AO VIVO, eliminado), mesa e assentos.
- **Regras vêm de `docs/regras.md`:** os protótipos foram feitos antes de fechar o modelo de dinheiro. Onde a tela mostrar algo diferente (ex.: cadastro do torneio, tela 20, e escolha de buy-in, tela 08b), vale o `regras.md`: Entradas com variações (+), Opcionais com destino (não vai / vai / 50-50) e Taxas tiradas da premiação.
- Os textos e números são de exemplo (Poker Night #18).
- Não copiar o JS dos protótipos para o projeto: ele só simula navegação. A lógica é Livewire + Actions.

Tokens, CSS dos componentes (`bundle.css`, classes `rh-`), o JS de referência da mesa (`RHG.pokerTable` em `bundle.js`) e as fontes ficam em `docs/design-system/`.
