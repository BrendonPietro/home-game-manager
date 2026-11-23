# Rodrigues Home Game

[![Testes](https://github.com/BrendonPietro/home-game-manager/actions/workflows/tests.yml/badge.svg)](https://github.com/BrendonPietro/home-game-manager/actions/workflows/tests.yml)

🇺🇸 [English version](README.en.md)

Sistema de gerenciamento de um home game semanal de poker **presencial**. Substitui a planilha da noite: torneios e cash games, jogadores e mesas, buy-ins, rebuys, add-ons e opcionais, pagamentos, prize pool, premiação, acertos de "quem paga quem", rankings e o relógio do torneio na TV.

Um app Laravel com duas entradas:

- **Site de operação** (`/`): usado na mesa durante a noite, no notebook, no tablet ou no celular. Mesa com os lugares, painel de cada jogador (entrada, pagamento, eliminação), fases do torneio, resumo e fechamento.
- **Painel administrativo** (`/painel`): usado no PC para cadastrar jogos e modelos, a lista de confirmados com os pagamentos antecipados, estruturas de blinds, rankings, calendário, aluguéis de mesa, relatórios e permissões.

## O que ele faz numa noite

1. **Antes**: o jogo é cadastrado no painel (entradas, opcionais, taxas, premiação e blinds), com a lista de confirmados e quem já pagou.
2. **Na mesa**: cada um que senta já entra com o buy-in. Quem cai pode voltar com rebuy, na hora ou no intervalo. Pagamentos parciais ou totais, em Pix ou Dinheiro, a qualquer momento.
3. **O relógio**: anda sozinho no navegador, mesmo sem internet, e vai para a TV por um link sem login.
4. **Fases**: ABRIR INTERVALO (abre o add-on; o rebuy continua) e FECHAR ENTRADAS (grava os prêmios; cada eliminação já dá a posição).
5. **Fechamento**: confere arrecadação, taxas, prize pool e prêmios, sugere os acertos e grava os pontos dos rankings.
6. **Correções**: voltar uma fase ou desfazer um pagamento pede motivo e fica no histórico do jogo (quem, o quê e quando).

Contas sempre em centavos inteiros e nada é apagado: desfazer só marca o registro como cancelado.

## Stack

PHP 8.5, Laravel 13, Filament 5 (painel), Livewire 4 + Alpine.js (site), SCSS com o design system do clube, Vite e MySQL. Sem WebSocket, fila nem cron: roda em hospedagem compartilhada (as telas ao vivo usam polling).

## Rodar local

```bash
composer install
npm install
cp .env.example .env          # ajuste o banco e as senhas iniciais (PAINEL_SENHA_INICIAL, SITE_SENHA_INICIAL)
php artisan key:generate
php artisan migrate --seed    # no APP_ENV=local cria também uma noite de exemplo
npm run build                 # ou npm run dev
php artisan serve
```

O seed cria a conta `dev` do painel e `dev@rodrigues.test` do site. Sem a senha no `.env`, ela é gerada e mostrada no terminal.

## Testes

```bash
php artisan test --parallel
```

A noite de exemplo (12 jogadores, 22 entradas) é a referência das contas: R$ 2.100 arrecadados, R$ 210 de taxa e R$ 1.890 de prize pool, pago como 950 / 565 / 375. Os testes conferem esses números pela calculadora e jogando a noite inteira pelas Actions.

## Documentação

- [docs/arquitetura.md](docs/arquitetura.md): estrutura de pastas, rotas e telas
- [docs/regras.md](docs/regras.md): regras da noite, cálculos, acertos, permissões e o relógio
- [docs/painel.md](docs/painel.md): o painel Filament
- [docs/design-system.md](docs/design-system.md): o design system e as regras do visual
- [docs/deploy.md](docs/deploy.md): ambiente local, deploy e backup
- [docs/roteiro-de-teste.md](docs/roteiro-de-teste.md): teste à mão do sistema inteiro, na ordem da noite
- [CHANGELOG.md](CHANGELOG.md): o que mudou em cada versão
