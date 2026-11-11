// Conexão das telas da noite (Livewire). Sem isso, um toque que não chega ao servidor some calado:
// o botão volta ao normal e parece que gravou.
//
// - O poll (tela ao vivo, resumo) falhou ou o aparelho ficou sem rede: faixa "Sem conexão", que some
//   sozinha no primeiro pedido que volta a dar certo.
// - Um toque (lançamento, pagamento, botão de fase…) falhou: faixa vermelha dizendo que não foi
//   gravado, que fica até alguém fechar ou até o próximo toque dar certo.
// - Sessão vencida (419): recarrega a página em vez do aviso em inglês do Livewire.
// - O que a tela mostrava não existe mais (404, ex.: o jogo foi excluído com a tela aberta): recarrega,
//   e o servidor leva para o início com o aviso (App\Support\PaginaQueNaoExiste).

const TEXTO = {
    poll: 'Sem conexão com o servidor · tentando de novo',
    acao: 'O último toque não chegou ao servidor: confira e tente de novo',
};

let faixa = null;
let estado = null; // null, 'poll' ou 'acao'

function montar() {
    faixa = document.createElement('div');
    faixa.className = 'rh-conexao';
    faixa.setAttribute('role', 'status');
    faixa.setAttribute('aria-live', 'polite');
    faixa.hidden = true;
    faixa.innerHTML = '<span class="rh-conexao__ponto" aria-hidden="true"></span><span class="rh-conexao__texto"></span>'
        + '<button type="button" class="rh-btn rh-btn--sm rh-btn--ghost rh-conexao__fechar">Fechar</button>';
    faixa.querySelector('button').addEventListener('click', esconder);
    document.body.appendChild(faixa);
}

function mostrar(tipo) {
    // O aviso de toque perdido é o mais importante: um poll que falha depois não o troca.
    if (estado === 'acao' && tipo === 'poll') {
        return;
    }

    if (!faixa) {
        montar();
    }

    estado = tipo;
    faixa.classList.toggle('rh-conexao--acao', tipo === 'acao');
    faixa.querySelector('.rh-conexao__texto').textContent = TEXTO[tipo];
    faixa.querySelector('button').hidden = tipo !== 'acao';
    faixa.hidden = false;
}

function esconder() {
    estado = null;

    if (faixa) {
        faixa.hidden = true;
    }
}

// Pedido só de atualização (wire:poll ou $refresh), sem nada que a pessoa tenha tocado.
function soAtualizacao(request) {
    const mensagens = Array.from(request.messages ?? []);

    return mensagens.length > 0 && mensagens.every((mensagem) => {
        const acoes = Array.from(mensagem.actions ?? []);

        return acoes.length > 0 && acoes.every((acao) => acao.name === '$refresh');
    });
}

export function vigiarConexao(Livewire) {
    Livewire.interceptRequest(({ request, onFailure, onError, onSuccess }) => {
        const atualizacao = soAtualizacao(request);

        onFailure(() => mostrar(atualizacao ? 'poll' : 'acao'));

        onSuccess(() => {
            // Um poll que volta só tira o "sem conexão"; o aviso de toque perdido sai com o próximo toque.
            if (estado === 'poll' || !atualizacao) {
                esconder();
            }
        });

        onError(({ response, preventDefault }) => {
            if (response?.status === 419 || response?.status === 404) {
                preventDefault();
                window.location.reload();
            }
        });
    });

    window.addEventListener('offline', () => mostrar('poll'));
}
