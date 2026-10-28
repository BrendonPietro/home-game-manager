<?php

namespace App\Services\Relatorios;

use App\Enums\StatusAluguel;
use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Models\Aluguel;
use App\Models\Entrada;
use App\Models\Jogo;
use App\Models\Participante;
use App\Services\Jogo\Calculadora;
use App\Services\Jogo\ResumoFinanceiro;
use App\Services\Jogo\Saldos;
use App\Services\Painel\Indicadores;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Os relatórios do painel (tela 24). Dinheiro em centavos, pela Calculadora e pelos Saldos.
 *
 * - Jogos: todos menos rascunho. Entradas, prize pools e arrecadação: em andamento e finalizados.
 *   Premiações: só finalizados (as posições e os prêmios já estão gravados).
 * - O período vale pela data do jogo (e do evento, nos aluguéis). Jogo e jogador filtram os jogos;
 *   aluguéis e arrecadação não usam esses dois.
 */
class Relatorios
{
    /** tipo => [título, descrição, ícone] na ordem da tela */
    public const TIPOS = [
        'jogos' => ['Jogos', 'Datas, jogadores, mesas e status', 'rh-trophy'],
        'entradas' => ['Entradas', 'Buy-ins, rebuys e add-ons por noite', 'rh-coins'],
        'prize_pools' => ['Prize pools', 'Arrecadado, taxas e prêmio por jogo', 'rh-chart'],
        'premiacoes' => ['Premiações', 'Quem ficou ITM e quanto levou', 'rh-trophy'],
        'alugueis' => ['Aluguéis', 'Reservas, pagos e pendentes', 'rh-key'],
        'arrecadacao' => ['Arrecadação', 'Taxas, fora da premiação e aluguéis', 'rh-dash'],
    ];

    private const SEM_RASCUNHO = [StatusJogo::Agendado, StatusJogo::EmAndamento, StatusJogo::Finalizado, StatusJogo::Cancelado];

    private const COM_DINHEIRO = [StatusJogo::EmAndamento, StatusJogo::Finalizado];

    public function __construct(
        private Calculadora $calculadora,
        private Saldos $saldos,
    ) {}

    public function gerar(string $tipo, Filtro $filtro): Relatorio
    {
        return match ($tipo) {
            'jogos' => $this->jogos($filtro),
            'entradas' => $this->entradas($filtro),
            'prize_pools' => $this->prizePools($filtro),
            'premiacoes' => $filtro->usuarioId ? $this->premiacoesDoJogador($filtro) : $this->premiacoes($filtro),
            'alugueis' => $this->alugueis($filtro),
            'arrecadacao' => $this->arrecadacao($filtro),
            default => throw new InvalidArgumentException("Relatório desconhecido: {$tipo}"),
        };
    }

    private function jogos(Filtro $filtro): Relatorio
    {
        $jogos = $this->consultaDeJogos($filtro, self::SEM_RASCUNHO)->get();
        $resumos = $jogos->mapWithKeys(fn (Jogo $jogo): array => [$jogo->id => $this->calculadora->doJogo($jogo)]);

        $linhas = $jogos->map(fn (Jogo $jogo): array => [
            'data' => $jogo->data,
            'jogo' => $jogo->nome,
            'modalidade' => $jogo->modalidade->label(),
            'status' => $jogo->status->label(),
            'confirmados' => $jogo->participantes_count,
            'jogadores' => $resumos[$jogo->id]->jogadores,
            'entradas' => $resumos[$jogo->id]->entradas,
            'mesas' => $jogo->mesas_count,
            'arrecadado' => $resumos[$jogo->id]->arrecadacao,
        ])->all();

        // Jogador = quem tem buy-in válido (como na Calculadora).
        $diferentes = Participante::query()
            ->whereIn('jogo_id', $jogos->modelKeys())
            ->whereHas('entradas', fn (Builder $q) => $q->validas()->where('tipo', TipoItem::Buyin))
            ->distinct()
            ->count('usuario_id');

        return new Relatorio('jogos', 'Jogos', $filtro->periodo, [
            new Coluna('data', 'Data', Coluna::DATA),
            new Coluna('jogo', 'Jogo'),
            new Coluna('modalidade', 'Modalidade'),
            new Coluna('status', 'Status'),
            new Coluna('confirmados', 'Confirmados', Coluna::INTEIRO),
            new Coluna('jogadores', 'Jogadores', Coluna::INTEIRO),
            new Coluna('entradas', 'Entradas', Coluna::INTEIRO),
            new Coluna('mesas', 'Mesas', Coluna::INTEIRO),
            new Coluna('arrecadado', 'Arrecadado', Coluna::DINHEIRO),
        ], $linhas, [
            ['rotulo' => 'Jogos', 'valor' => (string) $jogos->count(), 'detalhe' => self::plural($jogos->where('status', StatusJogo::Finalizado)->count(), 'finalizado', 'finalizados')],
            ['rotulo' => 'Jogadores diferentes', 'valor' => (string) $diferentes],
            ['rotulo' => 'Entradas', 'valor' => (string) $resumos->sum('entradas'), 'detalhe' => 'buy-ins, rebuys e add-ons'],
            ['rotulo' => 'Arrecadado', 'valor' => Dinheiro::brlCentavos($resumos->sum('arrecadacao'))],
        ]);
    }

    private function entradas(Filtro $filtro): Relatorio
    {
        $jogos = $this->consultaDeJogos($filtro, self::COM_DINHEIRO)->get();

        $entradas = Entrada::query()->validas()
            ->whereIn('jogo_id', $jogos->modelKeys())
            ->when($filtro->usuarioId, fn (Builder $q, int $usuario) => $q->whereHas('participante', fn (Builder $p) => $p->where('usuario_id', $usuario)))
            ->get(['jogo_id', 'tipo', 'valor'])
            ->groupBy('jogo_id');

        $porTipo = fn (Collection $linhas, TipoItem $tipo): Collection => $linhas->where('tipo', $tipo);
        $soma = fn (Collection $linhas): int => $linhas->sum(fn (Entrada $e): int => Dinheiro::centavos($e->valor));

        $linhas = $jogos->map(function (Jogo $jogo) use ($entradas, $porTipo, $soma): array {
            $doJogo = $entradas->get($jogo->id, collect());

            return [
                'data' => $jogo->data,
                'jogo' => $jogo->nome,
                'buyins' => $porTipo($doJogo, TipoItem::Buyin)->count(),
                'rebuys' => $porTipo($doJogo, TipoItem::Rebuy)->count(),
                'addons' => $porTipo($doJogo, TipoItem::Addon)->count(),
                'opcionais' => $porTipo($doJogo, TipoItem::Opcional)->count(),
                'total' => $soma($doJogo),
            ];
        })->all();

        $todas = $entradas->flatten(1);
        $nomes = ['buyin' => 'Buy-ins', 'rebuy' => 'Rebuys', 'addon' => 'Add-ons', 'opcional' => 'Opcionais'];
        $resumo = array_map(fn (TipoItem $tipo): array => [
            'rotulo' => $nomes[$tipo->value],
            'valor' => (string) $porTipo($todas, $tipo)->count(),
            'detalhe' => Dinheiro::brlCentavos($soma($porTipo($todas, $tipo))),
        ], TipoItem::cases());

        return new Relatorio('entradas', 'Entradas', $filtro->periodo, [
            new Coluna('data', 'Data', Coluna::DATA),
            new Coluna('jogo', 'Jogo'),
            new Coluna('buyins', 'Buy-ins', Coluna::INTEIRO),
            new Coluna('rebuys', 'Rebuys', Coluna::INTEIRO),
            new Coluna('addons', 'Add-ons', Coluna::INTEIRO),
            new Coluna('opcionais', 'Opcionais', Coluna::INTEIRO),
            new Coluna('total', 'Total', Coluna::DINHEIRO),
        ], $linhas, $resumo, $filtro->usuarioId ? 'Só as entradas do jogador escolhido.' : null);
    }

    private function prizePools(Filtro $filtro): Relatorio
    {
        $jogos = $this->consultaDeJogos($filtro, self::COM_DINHEIRO)->get();
        $resumos = $jogos->map(fn (Jogo $jogo): array => [$jogo, $this->calculadora->doJogo($jogo)]);

        $linhas = $resumos->map(fn (array $par): array => [
            'data' => $par[0]->data,
            'jogo' => $par[0]->nome,
            'jogadores' => $par[1]->jogadores,
            'arrecadado' => $par[1]->arrecadacao,
            'taxas' => $par[1]->totalTaxas,
            'fora' => $par[1]->foraDaPremiacao,
            'prize_pool' => $par[1]->prizePool,
        ])->all();

        $total = fn (string $campo): int => $resumos->sum(fn (array $par): int => $par[1]->{$campo});

        return new Relatorio('prize_pools', 'Prize pools', $filtro->periodo, [
            new Coluna('data', 'Data', Coluna::DATA),
            new Coluna('jogo', 'Jogo'),
            new Coluna('jogadores', 'Jogadores', Coluna::INTEIRO),
            new Coluna('arrecadado', 'Arrecadado', Coluna::DINHEIRO),
            new Coluna('taxas', 'Taxas', Coluna::DINHEIRO),
            new Coluna('fora', 'Fora da premiação', Coluna::DINHEIRO),
            new Coluna('prize_pool', 'Prize pool', Coluna::DINHEIRO),
        ], $linhas, [
            ['rotulo' => 'Arrecadado', 'valor' => Dinheiro::brlCentavos($total('arrecadacao')), 'detalhe' => self::plural($resumos->count(), 'jogo', 'jogos')],
            ['rotulo' => 'Taxas', 'valor' => Dinheiro::brlCentavos($total('totalTaxas')), 'detalhe' => 'dealers, casa…'],
            ['rotulo' => 'Fora da premiação', 'valor' => Dinheiro::brlCentavos($total('foraDaPremiacao')), 'detalhe' => 'opcionais que não vão para o prêmio'],
            ['rotulo' => 'Prize pools', 'valor' => Dinheiro::brlCentavos($total('prizePool'))],
        ]);
    }

    /** Por jogador, do melhor resultado para o pior. */
    private function premiacoes(Filtro $filtro): Relatorio
    {
        [$jogos, $contas] = $this->contasDosJogadores($filtro);

        $linhas = $contas->groupBy('usuario_id')
            ->map(fn (Collection $doJogador): array => [
                'jogador' => $doJogador->first()['nome'],
                'jogos' => $doJogador->count(),
                'investido' => $doJogador->sum('investido'),
                'premiacao' => $doJogador->sum('premio'),
                'resultado' => $doJogador->sum('premio') - $doJogador->sum('investido'),
                'itm' => $doJogador->where('itm', true)->count(),
            ])
            ->sort(fn (array $a, array $b): int => [$b['resultado'], $b['premiacao'], $a['jogador']] <=> [$a['resultado'], $a['premiacao'], $b['jogador']])
            ->values()
            ->map(fn (array $linha, int $i): array => ['lugar' => $i + 1] + $linha)
            ->all();

        $maior = $contas->where('itm', true)->sortByDesc('premio')->first();

        return new Relatorio('premiacoes', 'Premiações', $filtro->periodo, [
            new Coluna('lugar', '#', Coluna::INTEIRO),
            new Coluna('jogador', 'Jogador'),
            new Coluna('jogos', 'Jogos', Coluna::INTEIRO),
            new Coluna('investido', 'Investido', Coluna::DINHEIRO),
            new Coluna('premiacao', 'Premiação', Coluna::DINHEIRO),
            new Coluna('resultado', 'Resultado', Coluna::RESULTADO),
            new Coluna('itm', 'ITM', Coluna::INTEIRO),
        ], $linhas, [
            ['rotulo' => 'Jogos', 'valor' => (string) $jogos->count(), 'detalhe' => 'finalizados'],
            ['rotulo' => 'Prêmios pagos', 'valor' => Dinheiro::brlCentavos($contas->sum('premio')), 'detalhe' => 'prêmios e cash-outs'],
            ['rotulo' => 'Taxas acumuladas', 'valor' => Dinheiro::brlCentavos($jogos->sum(fn (Jogo $j): int => $this->calculadora->doJogo($j)->totalTaxas))],
            ['rotulo' => 'Maior prêmio', 'valor' => Dinheiro::brlCentavos($maior['premio'] ?? 0), 'detalhe' => $maior ? $maior['nome'].' · '.Indicadores::rotulo($maior['jogo']) : '—'],
        ], 'Ordenado pelo resultado (premiação − investido).');
    }

    /** Com um jogador escolhido: uma linha por jogo dele. */
    private function premiacoesDoJogador(Filtro $filtro): Relatorio
    {
        [, $contas] = $this->contasDosJogadores($filtro);
        $contas = $contas->where('usuario_id', $filtro->usuarioId)->values();

        $linhas = $contas->map(fn (array $conta): array => [
            'data' => $conta['jogo']->data,
            'jogo' => $conta['jogo']->nome,
            'posicao' => $conta['jogo']->eTorneio() ? ($conta['posicao'] ? $conta['posicao'].'º' : null) : 'cash game',
            'investido' => $conta['investido'],
            'premiacao' => $conta['premio'],
            'resultado' => $conta['premio'] - $conta['investido'],
        ])->all();

        $resultado = $contas->sum('premio') - $contas->sum('investido');

        return new Relatorio('premiacoes', 'Premiações de '.($contas->first()['nome'] ?? 'jogador'), $filtro->periodo, [
            new Coluna('data', 'Data', Coluna::DATA),
            new Coluna('jogo', 'Jogo'),
            new Coluna('posicao', 'Posição'),
            new Coluna('investido', 'Investido', Coluna::DINHEIRO),
            new Coluna('premiacao', 'Premiação', Coluna::DINHEIRO),
            new Coluna('resultado', 'Resultado', Coluna::RESULTADO),
        ], $linhas, [
            ['rotulo' => 'Jogos', 'valor' => (string) $contas->count(), 'detalhe' => self::plural($contas->where('itm', true)->count(), 'ITM', 'ITM')],
            ['rotulo' => 'Investido', 'valor' => Dinheiro::brlCentavos($contas->sum('investido'))],
            ['rotulo' => 'Premiação', 'valor' => Dinheiro::brlCentavos($contas->sum('premio'))],
            ['rotulo' => 'Resultado', 'valor' => Dinheiro::brlCentavos($resultado, true)],
        ]);
    }

    private function alugueis(Filtro $filtro): Relatorio
    {
        $alugueis = Aluguel::query()
            ->with('evento.mesas')
            ->whereHas('evento', fn (Builder $q) => $this->noPeriodo($q, $filtro))
            ->get()
            ->sortBy(fn (Aluguel $a): string => $a->evento->data->toDateString().' '.$a->evento->hora_inicio)
            ->values();

        $linhas = $alugueis->map(fn (Aluguel $aluguel): array => [
            'data' => $aluguel->evento->data,
            'cliente' => $aluguel->cliente,
            'mesas' => $aluguel->evento->nomesDasMesas(),
            'horario' => $aluguel->evento->horario(),
            'valor' => Dinheiro::centavos($aluguel->valor),
            'status' => $aluguel->status->label(),
            'pago_em' => $aluguel->pago_em,
        ])->all();

        $comStatus = fn (StatusAluguel ...$status): Collection => $alugueis->filter(fn (Aluguel $a): bool => in_array($a->status, $status, true));
        $cartao = fn (string $rotulo, Collection $grupo): array => [
            'rotulo' => $rotulo,
            'valor' => Dinheiro::brlCentavos($grupo->sum(fn (Aluguel $a): int => Dinheiro::centavos($a->valor))),
            'detalhe' => self::plural($grupo->count(), 'aluguel', 'aluguéis'),
        ];

        return new Relatorio('alugueis', 'Aluguéis', $filtro->periodo, [
            new Coluna('data', 'Data', Coluna::DATA),
            new Coluna('cliente', 'Cliente'),
            new Coluna('mesas', 'Mesas'),
            new Coluna('horario', 'Horário'),
            new Coluna('valor', 'Valor', Coluna::DINHEIRO),
            new Coluna('status', 'Status'),
            new Coluna('pago_em', 'Pago em', Coluna::DATA),
        ], $linhas, [
            $cartao('Pago', $comStatus(StatusAluguel::Pago)),
            $cartao('A receber', $comStatus(StatusAluguel::Reservado, StatusAluguel::Confirmado)),
            $cartao('Cancelado', $comStatus(StatusAluguel::Cancelado)),
            $cartao('Total sem cancelados', $comStatus(StatusAluguel::Reservado, StatusAluguel::Confirmado, StatusAluguel::Pago)),
        ], self::avisoSemJogoNemJogador($filtro));
    }

    /** Por mês: o que entrou nos jogos e nos aluguéis e o que ficou fora dos prêmios. */
    private function arrecadacao(Filtro $filtro): Relatorio
    {
        $semJogoNemJogador = new Filtro($filtro->de, $filtro->ate, periodo: $filtro->periodo);
        $mes = fn (CarbonInterface $data): string => $data->format('Y-m');

        $jogos = $this->consultaDeJogos($semJogoNemJogador, self::COM_DINHEIRO)->get()
            ->map(fn (Jogo $jogo): array => ['mes' => $mes($jogo->data), 'resumo' => $this->calculadora->doJogo($jogo)])
            ->groupBy('mes');

        $alugueis = Aluguel::query()
            ->with('evento')
            ->where('status', StatusAluguel::Pago)
            ->whereHas('evento', fn (Builder $q) => $this->noPeriodo($q, $filtro))
            ->get()
            ->groupBy(fn (Aluguel $a): string => $mes($a->evento->data));

        $meses = $jogos->keys()->merge($alugueis->keys())->unique()->sort()->values();

        $linhas = $meses->map(function (string $chave) use ($jogos, $alugueis): array {
            $resumos = $jogos->get($chave, collect())->pluck('resumo');
            $soma = fn (string $campo): int => $resumos->sum(fn (ResumoFinanceiro $r): int => $r->{$campo});
            $pagos = $alugueis->get($chave, collect())->sum(fn (Aluguel $a): int => Dinheiro::centavos($a->valor));

            return [
                'mes' => Str::ucfirst(CarbonImmutable::createFromFormat('!Y-m', $chave)->translatedFormat('M/Y')),
                'jogos' => $resumos->count(),
                'arrecadado' => $soma('arrecadacao'),
                'prize_pools' => $soma('prizePool'),
                'taxas' => $soma('totalTaxas'),
                'fora' => $soma('foraDaPremiacao'),
                'alugueis' => $pagos,
                'fora_dos_premios' => $soma('totalTaxas') + $soma('foraDaPremiacao') + $pagos,
            ];
        })->all();

        $total = fn (string $campo): int => array_sum(array_column($linhas, $campo));

        return new Relatorio('arrecadacao', 'Arrecadação', $filtro->periodo, [
            new Coluna('mes', 'Mês'),
            new Coluna('jogos', 'Jogos', Coluna::INTEIRO),
            new Coluna('arrecadado', 'Arrecadado nos jogos', Coluna::DINHEIRO),
            new Coluna('prize_pools', 'Prize pools', Coluna::DINHEIRO),
            new Coluna('taxas', 'Taxas', Coluna::DINHEIRO),
            new Coluna('fora', 'Fora da premiação', Coluna::DINHEIRO),
            new Coluna('alugueis', 'Aluguéis pagos', Coluna::DINHEIRO),
            new Coluna('fora_dos_premios', 'Fora dos prêmios', Coluna::DINHEIRO),
        ], $linhas, [
            ['rotulo' => 'Taxas', 'valor' => Dinheiro::brlCentavos($total('taxas'))],
            ['rotulo' => 'Fora da premiação', 'valor' => Dinheiro::brlCentavos($total('fora'))],
            ['rotulo' => 'Aluguéis pagos', 'valor' => Dinheiro::brlCentavos($total('alugueis'))],
            ['rotulo' => 'Fora dos prêmios', 'valor' => Dinheiro::brlCentavos($total('fora_dos_premios')), 'detalhe' => 'taxas + fora da premiação + aluguéis'],
        ], self::avisoSemJogoNemJogador($filtro));
    }

    /**
     * Conta de cada jogador em cada jogo finalizado do filtro (quem não comprou nada fica de fora).
     *
     * @return array{Collection<int, Jogo>, Collection<int, array{jogo: Jogo, usuario_id: int, nome: string, posicao: ?int, investido: int, premio: int, itm: bool}>}
     */
    private function contasDosJogadores(Filtro $filtro): array
    {
        $jogos = $this->consultaDeJogos($filtro, [StatusJogo::Finalizado])->with('participantes.usuario')->get();

        $contas = $jogos->flatMap(fn (Jogo $jogo): Collection => $this->saldos->doJogo($jogo)
            ->filter(fn ($saldo): bool => $saldo->investido > 0 || $saldo->premio > 0)
            ->map(function ($saldo) use ($jogo): array {
                $participante = $jogo->participantes->firstWhere('id', $saldo->participanteId);

                return [
                    'jogo' => $jogo,
                    'usuario_id' => $participante->usuario_id,
                    'nome' => $participante->usuario->nome_exibicao,
                    'posicao' => $participante->posicao_final,
                    'investido' => $saldo->investido,
                    'premio' => $saldo->premio,
                    'itm' => $jogo->eTorneio() && $saldo->premio > 0,
                ];
            })
            ->values());

        return [$jogos, $contas];
    }

    /** @param  list<StatusJogo>  $status */
    private function consultaDeJogos(Filtro $filtro, array $status): Builder
    {
        return $this->noPeriodo(Jogo::query()->reais()->comStatus(...$status), $filtro)
            ->when($filtro->jogoId, fn (Builder $q, int $jogo) => $q->whereKey($jogo))
            ->when($filtro->usuarioId, fn (Builder $q, int $usuario) => $q->whereHas('participantes', fn (Builder $p) => $p->where('usuario_id', $usuario)))
            ->with('taxas', 'premiacoes')
            ->withCount('participantes', 'mesas')
            ->orderBy('data')
            ->orderBy('id');
    }

    /** Coluna `data` no período (whereDate: no SQLite a data vem com hora). */
    private function noPeriodo(Builder $query, Filtro $filtro): Builder
    {
        return $query
            ->when($filtro->de, fn (Builder $q, CarbonImmutable $de) => $q->whereDate('data', '>=', $de))
            ->when($filtro->ate, fn (Builder $q, CarbonImmutable $ate) => $q->whereDate('data', '<=', $ate));
    }

    private static function avisoSemJogoNemJogador(Filtro $filtro): ?string
    {
        return $filtro->jogoId || $filtro->usuarioId ? 'Este relatório não usa os filtros de jogo e de jogador.' : null;
    }

    private static function plural(int $n, string $um, string $varios): string
    {
        return $n === 1 ? "1 {$um}" : "{$n} {$varios}";
    }
}
