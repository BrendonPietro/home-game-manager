<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Enums\TipoAlteracao;
use App\Exceptions\RegraDoJogo;
use App\Models\Alteracao;
use App\Models\Entrada;
use App\Models\Item;
use App\Models\Jogo;
use App\Models\Seguranca;
use App\Services\Jogo\Calculadora;
use App\Services\Jogo\Historico;
use App\Services\Jogo\Posicoes;
use App\Services\Jogo\ResumoFinanceiro;
use App\Support\Dinheiro;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Jogo em andamento editado no painel (permissão `jogos.andamento`: Admin e Desenvolvedor). Depois de salvar:
 *
 * - as entradas já lançadas (buy-in, rebuy, add-on e opcionais, só as válidas) passam a valer o que o cadastro
 *   diz agora: valor, fichas e quanto vai para a premiação de cada item;
 * - no cash game, o cash-out de quem já saiu segue o valor da ficha;
 * - com as entradas fechadas, os prêmios de cada posição são regravados e as posições refeitas.
 *
 * O que cada jogador deve (Saldos) e o prize pool (Calculadora) saem das entradas e dos pagamentos, então
 * acompanham sozinhos. O que mudou vai para o histórico, como correção.
 */
class RefazerPeloCadastro
{
    use ChecaRegras;

    public const PERMISSAO = 'jogos.andamento';

    public function __construct(
        private Calculadora $calculadora,
        private Posicoes $posicoes,
        private Historico $historico,
    ) {}

    /** @param  ResumoFinanceiro|null  $antes  os números antes de salvar, para o histórico */
    public function __invoke(Jogo $jogo, Seguranca $quem, ?ResumoFinanceiro $antes = null): ?Alteracao
    {
        if (! $quem->ativo || ! $quem->can(self::PERMISSAO)) {
            throw new AuthorizationException('Só Admin e Desenvolvedor mudam o cadastro de um jogo em andamento.');
        }

        return DB::transaction(function () use ($jogo, $quem, $antes): ?Alteracao {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::EmAndamento], 'Só o jogo em andamento se refaz pelo cadastro.');

            $mudancas = [...$this->refazerEntradas($jogo), ...$this->refazerCashOuts($jogo)];

            $jogo->load('taxas', 'premiacoes');
            $depois = $this->calculadora->doJogo($jogo);

            if ($jogo->eTorneio() && $jogo->fase === FaseTorneio::SemEntradas) {
                if ($depois->premios === []) {
                    throw new RegraDoJogo("Nenhuma regra de premiação cobre {$depois->jogadores} jogadores: ajuste a premiação.");
                }

                $premiosAntes = $jogo->premiacoes->pluck('valor', 'posicao')->map(fn ($valor): int => Dinheiro::centavos($valor))->all();
                $this->posicoes->gravarPremios($jogo, $depois->premios);
                $this->posicoes->recalcular($jogo);

                if ($premiosAntes !== array_column($depois->premios, 'valor', 'posicao')) {
                    $mudancas[] = 'prêmios '.implode(' / ', array_map(fn (array $p): string => Dinheiro::brlCentavos($p['valor']), $depois->premios));
                }
            }

            if ($antes && $antes->prizePool !== $depois->prizePool) {
                $mudancas[] = 'prize pool '.Dinheiro::brlCentavos($antes->prizePool).' → '.Dinheiro::brlCentavos($depois->prizePool);
            }

            if ($mudancas === []) {
                return null;
            }

            return $this->historico->registrar($jogo, $quem, TipoAlteracao::CadastroAlterado,
                'Mudou o cadastro com o jogo em andamento: '.implode('; ', $mudancas),
                dados: ['antes' => $antes ? ['arrecadacao' => $antes->arrecadacao, 'prize_pool' => $antes->prizePool] : null,
                    'depois' => ['arrecadacao' => $depois->arrecadacao, 'prize_pool' => $depois->prizePool, 'premios' => $depois->premios]],
            );
        });
    }

    /** @return list<string> "Rebuy R$ 100 → R$ 120 (3 entradas)" */
    private function refazerEntradas(Jogo $jogo): array
    {
        $itens = $jogo->itens()->get()->keyBy('id');
        $mudancas = [];

        foreach ($jogo->entradas()->validas()->get()->groupBy('jogo_item_id') as $itemId => $doItem) {
            /** @var Item|null $item */
            $item = $itens->get($itemId);

            if ($item === null) {
                continue;
            }

            $diferentes = $doItem->filter(fn (Entrada $entrada): bool => Dinheiro::centavos($entrada->valor) !== Dinheiro::centavos($item->valor)
                || Dinheiro::centavos($entrada->percentual_premiacao) !== Dinheiro::centavos($item->percentual_premiacao)
                || (int) $entrada->fichas !== (int) $item->fichas);

            if ($diferentes->isEmpty()) {
                continue;
            }

            $exemplo = $diferentes->first();
            $partes = array_filter([
                Dinheiro::centavos($exemplo->valor) !== Dinheiro::centavos($item->valor)
                    ? Dinheiro::brl($exemplo->valor).' → '.Dinheiro::brl($item->valor) : null,
                Dinheiro::centavos($exemplo->percentual_premiacao) !== Dinheiro::centavos($item->percentual_premiacao)
                    ? 'premiação '.self::percentual($exemplo->percentual_premiacao).' → '.self::percentual($item->percentual_premiacao) : null,
                (int) $exemplo->fichas !== (int) $item->fichas
                    ? 'fichas '.number_format((int) $exemplo->fichas, 0, ',', '.').' → '.number_format((int) $item->fichas, 0, ',', '.') : null,
            ]);

            Entrada::query()->whereKey($diferentes->modelKeys())->update([
                'valor' => $item->valor,
                'percentual_premiacao' => $item->percentual_premiacao,
                'fichas' => $item->fichas,
            ]);

            $mudancas[] = $item->nome.' '.implode(', ', $partes).' ('.($diferentes->count() === 1 ? '1 entrada' : $diferentes->count().' entradas').')';
        }

        return $mudancas;
    }

    /** @return list<string> "cash-out refeito com a ficha a R$ 0,02 (2 jogadores)" */
    private function refazerCashOuts(Jogo $jogo): array
    {
        if (! $jogo->eCashGame() || (float) $jogo->valor_ficha <= 0) {
            return [];
        }

        $refeitos = 0;

        foreach ($jogo->participantes()->whereNotNull('fichas_finais')->get() as $participante) {
            $cashOut = Dinheiro::decimal(Dinheiro::multiplicar((int) $participante->fichas_finais, $jogo->valor_ficha));

            if (Dinheiro::centavos($participante->cash_out) !== Dinheiro::centavos($cashOut)) {
                $participante->forceFill(['cash_out' => $cashOut])->save();
                $refeitos++;
            }
        }

        return $refeitos ? ['cash-out refeito com a ficha a '.Dinheiro::ficha($jogo->valor_ficha).' ('.($refeitos === 1 ? '1 jogador' : $refeitos.' jogadores').')'] : [];
    }

    /** "75%", "92,5%" */
    private static function percentual(mixed $valor): string
    {
        return str_replace('.', ',', rtrim(rtrim(Dinheiro::decimal(Dinheiro::centavos($valor)), '0'), '.')).'%';
    }
}
