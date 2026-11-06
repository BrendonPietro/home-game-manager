// Tela acesa (Screen Wake Lock), para a TV e o celular de quem controla o relógio: só em https ou
// localhost. Sem isso, vale a configuração do aparelho. O navegador solta a trava quando a aba sai da
// frente; ao voltar, pede de novo.

let trava = null;
let ouvindo = false;

export async function manterAcesa() {
    if (!ouvindo) {
        ouvindo = true;
        document.addEventListener('visibilitychange', manterAcesa);
        document.addEventListener('fullscreenchange', manterAcesa);
    }

    if (!('wakeLock' in navigator) || document.visibilityState !== 'visible' || trava) {
        return;
    }

    try {
        trava = await navigator.wakeLock.request('screen');
        trava.addEventListener('release', () => {
            trava = null;
        });
    } catch {
        // Bateria fraca ou sem permissão: a tela pode apagar.
    }
}
