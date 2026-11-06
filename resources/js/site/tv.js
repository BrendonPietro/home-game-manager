// Link do relógio (/relogio/{codigo}, a TV): som, tela cheia, tela acesa e os botões que somem quando
// ninguém mexe.
//
// O som sai do próprio navegador (som.js). O navegador só deixa tocar depois de um toque na página:
// com o som ligado (fica no localStorage do aparelho) e sem toque ainda, aparece "Toque na tela para
// ligar o som". Toca a campainha quando o motor do relógio (relogio-torneio.js) passa para um nível novo
// (o tempo acabou ou alguém apertou PRÓXIMO; uma vez só por nível) e o AVISO quando o gerenciador
// aperta AVISO na tela do relógio dele.

import { aviso, campainha, contexto } from './som';
import { manterAcesa } from './tela-acesa';

const CHAVE = 'rh-tv-som';
const ESCONDE_EM = 3000; // ms sem mexer: os botões e o cursor somem

function lerSom() {
    try {
        return window.localStorage.getItem(CHAVE) === '1';
    } catch {
        return false;
    }
}

function salvarSom(ligado) {
    try {
        window.localStorage.setItem(CHAVE, ligado ? '1' : '0');
    } catch {
        // Aba anônima ou armazenamento bloqueado: vale só nesta visita.
    }
}

export default () => ({
    // Estado declarado (dentro deste componente fica o motor do relógio, x-data="relogioTorneio").
    som: false,
    travado: false, // som ligado, mas o navegador ainda não deixou tocar
    controles: true,
    cheia: false,
    podeTelaCheia: Boolean(document.fullscreenEnabled),
    esconder: null,
    aoMudarTela: null,

    init() {
        this.som = lerSom();
        this.travado = this.som;
        this.aoMudarTela = () => {
            this.cheia = Boolean(document.fullscreenElement);
        };
        document.addEventListener('fullscreenchange', this.aoMudarTela);

        this.mexeu();
        manterAcesa();
    },

    destroy() {
        document.removeEventListener('fullscreenchange', this.aoMudarTela);
        clearTimeout(this.esconder);
        document.body.classList.remove('rh-sem-cursor');
    },

    // Mexeu o mouse ou tocou: mostra os botões por uns segundos (o cursor some junto).
    mexeu() {
        this.controles = true;
        document.body.classList.remove('rh-sem-cursor');
        clearTimeout(this.esconder);
        this.esconder = setTimeout(() => {
            this.controles = false;
            document.body.classList.add('rh-sem-cursor');
        }, ESCONDE_EM);
    },

    // O primeiro toque na página libera o som (regra dos navegadores).
    destravar() {
        const ctx = this.som ? contexto() : null;

        if (!ctx) {
            this.travado = false;

            return Promise.resolve();
        }

        return ctx.resume().then(() => {
            this.travado = ctx.state !== 'running';
        });
    },

    alternarSom() {
        this.som = !this.som;
        salvarSom(this.som);

        if (this.som) {
            this.destravar().then(() => this.campainha()); // toca uma vez para conferir o volume
        } else {
            this.travado = false;
        }
    },

    // Som ligado e liberado pelo navegador.
    podeTocar() {
        return this.som && contexto()?.state === 'running';
    },

    campainha() {
        if (this.podeTocar()) {
            campainha(contexto());
        }
    },

    aviso() {
        if (this.podeTocar()) {
            aviso(contexto());
        }
    },

    async telaCheia() {
        try {
            if (document.fullscreenElement) {
                await document.exitFullscreen();
            } else {
                await document.documentElement.requestFullscreen();
            }
        } catch {
            // O navegador recusou (ex.: dentro de um iframe): segue na janela.
        }
    },
});
