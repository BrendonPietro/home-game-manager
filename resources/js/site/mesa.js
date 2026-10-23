// Mesa de poker (<x-site.poker.mesa>): posiciona os assentos por comprimento de arco na oval e gira em
// passos de 90°. Mesma conta do App\Support\PosicoesDaMesa (que dá a posição inicial no servidor).
//
// O contêiner tem wire:ignore.self: o Livewire redesenha os assentos, mas não mexe no style do
// contêiner, onde ficam --ar, --inset e --x0…--xN / --y0…--yN que os assentos usam.
// A rotação fica no localStorage do aparelho (cada mesa do jogo guarda a sua).

const AMOSTRAS = 720;

export function posicoes(lugares, ar, rx, ry, rotacao) {
    const pontos = [];
    const comprimento = [0];

    for (let s = 0; s <= AMOSTRAS; s++) {
        const t = ((90 + (s * 360) / AMOSTRAS) * Math.PI) / 180;
        pontos.push([rx * Math.cos(t), ry * Math.sin(t)]);

        if (s > 0) {
            const dx = (pontos[s][0] - pontos[s - 1][0]) * ar;
            const dy = pontos[s][1] - pontos[s - 1][1];
            comprimento.push(comprimento[s - 1] + Math.sqrt(dx * dx + dy * dy));
        }
    }

    const assentos = lugares + 1;

    return Array.from({ length: assentos }, (_, k) => {
        const fracao = ((((k / assentos + rotacao / 360) % 1) + 1) % 1) * comprimento[AMOSTRAS];
        let i = 0;

        while (i < AMOSTRAS && comprimento[i] < fracao) {
            i++;
        }

        return [50 + pontos[i][0], 50 + pontos[i][1]];
    });
}

function lerRotacao(chave, padrao) {
    try {
        const salva = window.localStorage.getItem(chave);

        return salva === null ? padrao : Number(salva) % 360;
    } catch {
        return padrao;
    }
}

function salvarRotacao(chave, rotacao) {
    try {
        window.localStorage.setItem(chave, String(rotacao));
    } catch {
        // Aba anônima ou armazenamento bloqueado: gira só nesta visita.
    }
}

export default (opcoes) => ({
    rotacao: 0,

    init() {
        this.chave = `rh-mesa-rotacao-${opcoes.id}`;
        // No celular a mesa abre em pé (docs/design-system.md).
        this.rotacao = lerRotacao(this.chave, window.innerWidth < 600 ? 90 : 0);
        this.desenhar();
        // Só anima depois do primeiro desenho: ao abrir, os assentos já aparecem no lugar.
        requestAnimationFrame(() => requestAnimationFrame(() => this.$el.classList.add('is-pronta')));

        let espera;
        this.aoRedimensionar = () => {
            clearTimeout(espera);
            espera = setTimeout(() => this.desenhar(), 120);
        };
        window.addEventListener('resize', this.aoRedimensionar);
    },

    destroy() {
        window.removeEventListener('resize', this.aoRedimensionar);
    },

    girar() {
        this.rotacao = (this.rotacao + 90) % 360;
        salvarRotacao(this.chave, this.rotacao);
        this.desenhar();
    },

    desenhar() {
        const retrato = this.rotacao % 180 === 90;
        const celular = window.innerWidth < 600;
        const ar = retrato ? (opcoes.arRetrato ?? (celular ? 0.6 : 0.82)) : (opcoes.ar ?? (celular ? 1.2 : 1.7));
        const rx = retrato ? opcoes.ry : opcoes.rx;
        const ry = retrato ? opcoes.rx : opcoes.ry;

        const estilo = this.$el.style;
        estilo.setProperty('--ar', ar);
        estilo.setProperty('--inset', retrato ? '9% 15%' : '15% 9%');

        posicoes(opcoes.lugares, ar, rx, ry, this.rotacao).forEach(([x, y], k) => {
            estilo.setProperty(`--x${k}`, `${x.toFixed(2)}%`);
            estilo.setProperty(`--y${k}`, `${y.toFixed(2)}%`);
        });

        this.$el.classList.toggle('is-retrato', retrato);
    },
});
