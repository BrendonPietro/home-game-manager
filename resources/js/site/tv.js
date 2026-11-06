// Tela da TV (Livewire Jogo\Tv): som na troca de nível, tela cheia, tela acesa e os botões que somem
// quando ninguém mexe.
//
// O som sai do próprio navegador (Web Audio, sem arquivo). O navegador só deixa tocar depois de um
// toque na página: com o som ligado (fica no localStorage do aparelho) e sem toque ainda, aparece o
// aviso "Toque na tela para ligar o som". Toca quando o motor do relógio (relogio-torneio.js) passa
// para um nível novo: o tempo acabou ou alguém apertou PRÓXIMO. O motor avisa uma vez só por nível.

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

// Uma nota com ataque curto e queda suave.
function nota(audio, frequencia, inicio, duracao) {
    const oscilador = audio.createOscillator();
    const volume = audio.createGain();

    oscilador.type = 'sine';
    oscilador.frequency.value = frequencia;
    volume.gain.setValueAtTime(0.0001, inicio);
    volume.gain.exponentialRampToValueAtTime(0.5, inicio + 0.02);
    volume.gain.exponentialRampToValueAtTime(0.0001, inicio + duracao);
    oscilador.connect(volume).connect(audio.destination);
    oscilador.start(inicio);
    oscilador.stop(inicio + duracao + 0.05);
}

export default () => ({
    // Estado declarado (dentro deste componente fica o motor do relógio, x-data="relogioTorneio").
    som: false,
    travado: false, // som ligado, mas o navegador ainda não deixou tocar
    controles: true,
    cheia: false,
    podeTelaCheia: Boolean(document.fullscreenEnabled),
    audio: null,
    trava: null,
    esconder: null,
    aoMudarTela: null,
    aoVoltar: null,

    init() {
        this.som = lerSom();
        this.travado = this.som;
        this.aoMudarTela = () => {
            this.cheia = Boolean(document.fullscreenElement);
            this.manterAcesa();
        };
        this.aoVoltar = () => this.manterAcesa();
        document.addEventListener('fullscreenchange', this.aoMudarTela);
        document.addEventListener('visibilitychange', this.aoVoltar);

        this.mexeu();
        this.manterAcesa();
    },

    destroy() {
        document.removeEventListener('fullscreenchange', this.aoMudarTela);
        document.removeEventListener('visibilitychange', this.aoVoltar);
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
        if (!this.som) {
            return Promise.resolve();
        }

        const Audio = window.AudioContext || window.webkitAudioContext;

        if (!Audio) {
            this.travado = false;

            return Promise.resolve();
        }

        this.audio ??= new Audio();

        return this.audio.resume().then(() => {
            this.travado = this.audio.state !== 'running';
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

    // Três notas subindo: troca de nível.
    campainha() {
        if (!this.som || this.audio?.state !== 'running') {
            return;
        }

        const inicio = this.audio.currentTime + 0.05;
        [660, 880, 1320].forEach((frequencia, i) => nota(this.audio, frequencia, inicio + i * 0.22, 0.6));
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

    // Tela acesa (Screen Wake Lock): só em https ou localhost. Sem isso, vale a configuração da TV.
    async manterAcesa() {
        if (!('wakeLock' in navigator) || document.visibilityState !== 'visible' || this.trava) {
            return;
        }

        try {
            this.trava = await navigator.wakeLock.request('screen');
            this.trava.addEventListener('release', () => {
                this.trava = null;
            });
        } catch {
            // Bateria fraca ou sem permissão: a tela pode apagar.
        }
    },
});
