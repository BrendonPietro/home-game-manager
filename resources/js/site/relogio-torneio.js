// Relógio do torneio no navegador (v3). O servidor manda o pacote de EstadoDoRelogio::paraNavegador()
// no atributo data-relogio do elemento: o nível de agora, os segundos que faltam, se está correndo e,
// para cada nível da estrutura, o que mostrar nele. Daqui em diante o navegador conta sozinho e passa
// para o próximo nível quando o tempo acaba, também sem internet. Cada atualização (o poll do
// Livewire troca o atributo) corrige a conta: o servidor é quem manda (pausa, próximo, anterior).
//
// Uma instância por tela, no contêiner (x-data="relogioTorneio"); dentro dele, os textos leem os
// getters (tempo, titulo, blinds, ante, depois, intervaloEmTexto…) com wire:ignore, para o poll não
// brigar com a contagem. Quando o nível muda para a frente, avisa com o evento relogio-trocou (a TV
// toca o som; a tela ao vivo pede ao servidor o resto da tela), uma vez só por nível.

//
// Fora do Livewire (link do relógio, /relogio/{codigo}), o contêiner traz data-url e data-chave: o
// motor busca o estado sozinho a cada 2 s, sem sessão. Quando a chave muda (o jogo começou, acabou, o
// nível digitado mudou) ou o link deixou de valer (404), a página recarrega; sem internet, segue contando.

const ACABANDO = 60; // último minuto do nível
const SEM_CONEXAO = 15000; // ms sem atualização: mostra "sem conexão" (o poll é de 5 s)
const VOLTOU_DE_VERDADE = 3; // s: o servidor voltar um nível com mais que isso é ANTERIOR, não atraso da rede
const BUSCA = 2000; // ms entre uma busca e outra, no link do relógio
const AVISO_RECENTE = 30; // s: aviso mais velho que isso (a tela estava sem internet) não toca
const AVISO_NA_TELA = 6000; // ms com o painel piscando "AVISO"

export function formatar(segundos) {
    const s = Math.max(0, segundos);
    const horas = Math.floor(s / 3600);
    const minutos = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
    const resto = String(s % 60).padStart(2, '0');

    return (horas > 0 ? `${horas}:` : '') + `${minutos}:${resto}`;
}

// "1 nível", "2 níveis"
const niveis = (n) => `${n} ${n === 1 ? 'nível' : 'níveis'}`;

export default () => ({
    // Todo o estado declarado aqui: dentro de outro componente, propriedade não declarada vai parar no
    // componente de fora (era o bug do relógio da TV, 0.7.4).
    temRelogio: false,
    niveis: [],
    indice: 0,
    restante: 0, // segundos que faltam, com o relógio parado
    fim: 0, // performance.now() em que o nível acaba, com o relógio correndo
    rodando: false,
    iniciado: false,
    segundos: 0, // segundos que faltam agora (o tique atualiza)
    tocado: -1, // maior nível que já avisou a troca
    atualizadoEm: 0,
    semConexao: false,
    tiques: null,
    observador: null,
    url: '',
    chave: '',
    proximaBusca: null,
    avisoVisto: -1, // número do último AVISO já visto (data-aviso ou o JSON do link)
    avisando: false,
    avisoTimer: null,

    init() {
        this.atualizadoEm = performance.now();
        this.lerAtributo(true);
        this.observador = new MutationObserver(() => this.lerAtributo(false));
        this.observador.observe(this.$el, { attributes: true, attributeFilter: ['data-relogio', 'data-aviso'] });
        this.tiques = setInterval(() => this.tique(), 250);

        this.url = this.$el.dataset.url ?? '';
        this.chave = this.$el.dataset.chave ?? '';

        if (this.url) {
            this.proximaBusca = setTimeout(() => this.buscar(), BUSCA);
        }
    },

    destroy() {
        this.observador?.disconnect();
        clearInterval(this.tiques);
        clearTimeout(this.proximaBusca);
        clearTimeout(this.avisoTimer);
    },

    // AVISO do gerenciador: toca (evento relogio-aviso) quando o número sobe e o aviso é recente.
    verAviso(aviso, primeira = false) {
        if (!aviso) {
            return;
        }

        if (primeira || this.avisoVisto < 0) {
            this.avisoVisto = aviso.numero;

            return;
        }

        if (aviso.numero <= this.avisoVisto) {
            return;
        }

        this.avisoVisto = aviso.numero;

        if (aviso.ha !== null && aviso.ha <= AVISO_RECENTE) {
            this.avisando = true;
            clearTimeout(this.avisoTimer);
            this.avisoTimer = setTimeout(() => {
                this.avisando = false;
            }, AVISO_NA_TELA);
            this.$dispatch('relogio-aviso');
        }
    },

    // Link do relógio: pede o estado ao servidor. Falhou (sem internet)? Segue contando e tenta de novo.
    async buscar() {
        try {
            const resposta = await fetch(this.url, { headers: { Accept: 'application/json' }, cache: 'no-store' });

            if (resposta.status === 404) {
                window.location.reload(); // link trocado ou jogo fora do site

                return;
            }

            if (resposta.ok) {
                const dados = await resposta.json();

                if (dados.chave !== this.chave) {
                    window.location.reload();

                    return;
                }

                if (dados.relogio) {
                    this.aplicar(dados.relogio);
                } else {
                    this.atualizadoEm = performance.now();
                }

                this.verAviso(dados.aviso);
            }
        } catch {
            // Sem internet: o relógio segue sozinho.
        }

        this.proximaBusca = setTimeout(() => this.buscar(), BUSCA);
    },

    lerAtributo(primeira) {
        const bruto = this.$el.dataset.relogio;
        const aviso = this.$el.dataset.aviso;

        try {
            if (bruto) {
                this.aplicar(JSON.parse(bruto), primeira);
            } else {
                this.temRelogio = false;
            }

            if (aviso) {
                this.verAviso(JSON.parse(aviso), primeira);
            }
        } catch {
            // Pacote quebrado: segue com a conta que já tinha.
        }
    },

    // Estado que veio do servidor.
    aplicar(pacote, primeira = false) {
        const antes = this.indice;

        this.niveis = pacote.niveis;
        this.indice = pacote.indice;
        this.rodando = pacote.rodando;
        this.iniciado = pacote.iniciado;
        this.restante = pacote.restante;
        this.fim = performance.now() + pacote.restante * 1000;
        this.temRelogio = this.niveis.length > 0;
        this.atualizadoEm = performance.now();

        // Na primeira leitura não avisa; voltar de verdade (ANTERIOR) libera o aviso dos níveis seguintes.
        if (primeira || (this.indice < antes && pacote.restante > VOLTOU_DE_VERDADE)) {
            this.tocado = this.indice;
        }

        this.tique(false);
    },

    tique(local = true) {
        if (!this.temRelogio) {
            return;
        }

        let segundos = this.rodando ? (this.fim - performance.now()) / 1000 : this.restante;

        // Anda pelos níveis que já acabaram (também os que passaram com a aba em segundo plano).
        while (this.rodando && segundos <= 0 && this.indice + 1 < this.niveis.length) {
            this.indice++;
            this.fim += this.niveis[this.indice].segundos * 1000;
            segundos = (this.fim - performance.now()) / 1000;
        }

        // Fim do último nível: para em 00:00, como no servidor.
        if (this.rodando && segundos <= 0) {
            this.rodando = false;
            this.restante = 0;
            segundos = 0;
        }

        this.segundos = Math.max(0, segundos);
        this.semConexao = performance.now() - this.atualizadoEm > SEM_CONEXAO;

        if (this.indice > this.tocado) {
            this.tocado = this.indice;
            this.$dispatch('relogio-trocou', { indice: this.indice, local });
        }
    },

    get nivel() {
        return this.niveis[this.indice] ?? null;
    },

    get tempo() {
        return formatar(Math.ceil(this.segundos));
    },

    get acabando() {
        return this.rodando && this.segundos <= ACABANDO;
    },

    get longo() {
        return this.segundos >= 3600;
    },

    get noIntervalo() {
        return Boolean(this.nivel?.intervalo);
    },

    get acabou() {
        return !this.rodando && this.segundos <= 0 && this.indice === this.niveis.length - 1;
    },

    get titulo() {
        return this.nivel?.titulo ?? '';
    },

    get rotulo() {
        return this.nivel?.rotulo ?? '';
    },

    get blinds() {
        return this.nivel?.blinds ?? '';
    },

    get ante() {
        return this.nivel?.ante ?? '';
    },

    get depois() {
        return this.nivel?.depois ?? '';
    },

    // Pausado, ainda não começou ou acabou a estrutura (correndo: vazio).
    get situacao() {
        if (!this.temRelogio || this.rodando) {
            return '';
        }

        if (this.acabou) {
            return 'Fim da estrutura';
        }

        return this.iniciado ? 'Pausado' : 'Aguardando o início';
    },

    // Segundos até o próximo intervalo começar (nulo no intervalo ou sem intervalo pela frente).
    get intervaloEm() {
        const ate = this.nivel?.ateIntervalo;

        return ate === null || ate === undefined || this.noIntervalo ? null : this.segundos + ate;
    },

    get intervaloEmTexto() {
        return this.intervaloEm === null ? '' : formatar(Math.ceil(this.intervaloEm));
    },

    // "2 níveis" (contando o de agora) até o intervalo.
    get niveisAteIntervalo() {
        const n = this.noIntervalo ? null : this.nivel?.niveisAteIntervalo;

        return n ? niveis(n) : '';
    },
});
