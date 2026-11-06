// Copia um texto para a área de transferência: $copiar(texto) no Alpine (devolve uma Promise).
// navigator.clipboard só existe em https e localhost; no http (o .test do Laragon, rede local) usa o
// jeito antigo, com um textarea escondido e execCommand('copy').
export default function copiar(texto) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(texto);
    }

    return new Promise((resolver, recusar) => {
        const campo = document.createElement('textarea');

        campo.value = texto;
        campo.setAttribute('readonly', '');
        campo.style.position = 'fixed';
        campo.style.opacity = '0';
        document.body.appendChild(campo);
        campo.select();

        try {
            document.execCommand('copy') ? resolver() : recusar(new Error('O navegador não deixou copiar.'));
        } catch (erro) {
            recusar(erro);
        } finally {
            campo.remove();
        }
    });
}
