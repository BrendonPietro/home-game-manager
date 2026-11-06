// Sons do relógio, gerados no navegador (Web Audio, sem arquivo): a campainha da troca de nível e o
// AVISO do gerenciador. Um AudioContext só por página. O navegador só deixa tocar depois de um toque
// na página: quem toca num clique (o botão AVISO) usa tocarAgora, que destrava e toca.

let audio = null;

export function contexto() {
    const Audio = window.AudioContext || window.webkitAudioContext;

    if (!Audio) {
        return null;
    }

    audio ??= new Audio();

    return audio;
}

// Uma nota com ataque curto e queda suave.
function nota(ctx, frequencia, inicio, duracao, tipo = 'sine', volume = 0.5) {
    const oscilador = ctx.createOscillator();
    const ganho = ctx.createGain();

    oscilador.type = tipo;
    oscilador.frequency.value = frequencia;
    ganho.gain.setValueAtTime(0.0001, inicio);
    ganho.gain.exponentialRampToValueAtTime(volume, inicio + 0.02);
    ganho.gain.exponentialRampToValueAtTime(0.0001, inicio + duracao);
    oscilador.connect(ganho).connect(ctx.destination);
    oscilador.start(inicio);
    oscilador.stop(inicio + duracao + 0.05);
}

// Três notas subindo: troca de nível.
export function campainha(ctx) {
    const inicio = ctx.currentTime + 0.05;

    [660, 880, 1320].forEach((frequencia, i) => nota(ctx, frequencia, inicio + i * 0.22, 0.6));
}

// AVISO: "ding-dong" três vezes, mais cheio que a campainha, para chamar a atenção da sala.
export function aviso(ctx) {
    const inicio = ctx.currentTime + 0.05;

    for (let i = 0; i < 3; i++) {
        nota(ctx, 988, inicio + i * 0.6, 0.28, 'triangle', 0.6);
        nota(ctx, 740, inicio + i * 0.6 + 0.28, 0.32, 'triangle', 0.6);
    }
}

// Toca num clique (gesto do usuário): destrava o áudio da página e toca.
export async function tocarAgora(som) {
    const ctx = contexto();

    if (!ctx) {
        return;
    }

    try {
        await ctx.resume();
        som(ctx);
    } catch {
        // Sem áudio no aparelho: segue sem som.
    }
}
