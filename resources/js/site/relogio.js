// Relógio do torneio na tela ao vivo (Services\Jogo\EstadoDoRelogio): conta no navegador entre uma
// atualização e outra (wire:poll de 5 s). O servidor manda os segundos que faltam (com a fração) e se
// está correndo em data-restante e data-rodando; cada atualização acerta a conta (o relógio do aparelho pode estar
// errado, então só vale o tempo que passou desde a resposta). Em 00:00 avisa a página (evento
// relogio-zerou, que a TV usa para o som) e pede uma atualização para o servidor passar de nível, sem
// esperar o próximo poll.
//
// O texto fica num elemento com wire:ignore e x-text="texto": o Livewire não briga com a contagem.

const ACABANDO = 60; // último minuto do nível

export function formatar(segundos) {
    const s = Math.max(0, segundos);
    const horas = Math.floor(s / 3600);
    const minutos = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
    const resto = String(s % 60).padStart(2, '0');

    return (horas > 0 ? `${horas}:` : '') + `${minutos}:${resto}`;
}

// Todo o estado fica declarado aqui: dentro de outro componente (a TV), o Alpine grava a propriedade
// que o objeto não declara no componente de fora, e os dois relógios da TV (o tempo e o "Intervalo em")
// passavam a dividir o mesmo restante.
export default () => ({
    texto: '',
    acabando: false,
    restante: 0,
    rodando: false,
    fim: 0,
    pediu: false,
    tiques: null,
    observador: null,

    init() {
        this.ler();
        this.observador = new MutationObserver(() => this.ler());
        this.observador.observe(this.$el, { attributes: true, attributeFilter: ['data-restante', 'data-rodando'] });
        this.tiques = setInterval(() => this.tique(), 250);
    },

    destroy() {
        this.observador?.disconnect();
        clearInterval(this.tiques);
    },

    ler() {
        this.restante = Number(this.$el.dataset.restante) || 0;
        this.rodando = this.$el.dataset.rodando === '1';
        this.fim = performance.now() + this.restante * 1000;
        this.pediu = false;
        this.tique();
    },

    tique() {
        const segundos = Math.max(0, Math.ceil(this.rodando ? (this.fim - performance.now()) / 1000 : this.restante));

        this.texto = formatar(segundos);
        this.acabando = this.rodando && segundos <= ACABANDO;

        if (this.rodando && segundos === 0 && !this.pediu) {
            this.pediu = true;
            this.$dispatch('relogio-zerou');
            setTimeout(() => this.$wire.$refresh(), 1000);
        }
    },
});
