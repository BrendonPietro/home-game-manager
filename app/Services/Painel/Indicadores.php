<?php

namespace App\Services\Painel;

use App\Enums\StatusAluguel;
use App\Enums\StatusJogo;
use App\Models\Aluguel;
use App\Models\Evento;
use App\Models\Jogo;
use App\Models\Mesa;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\Calculadora;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Números do dashboard do painel (tela 16). Dinheiro em centavos, pela Calculadora e pelos Saldos.
 *
 * "No mês" = jogos (em andamento ou finalizados) e aluguéis com data no mês. Pendências não têm mês:
 * saldo em aberto de jogador em jogo finalizado e aluguel não pago cuja data já chegou.
 */
class Indicadores
{
    /** Status que contam como jogo que aconteceu (ou vai acontecer). */
    private const NA_AGENDA = [StatusJogo::Agendado, StatusJogo::EmAndamento, StatusJogo::Finalizado];

    public function __construct(private Calculadora $calculadora) {}

    /** O jogo em andamento ou, sem ele, o próximo agendado. */
    public function proximoJogo(): ?Jogo
    {
        $consulta = fn (): Builder => Jogo::query()->reais()->withCount('participantes', 'mesas');

        return $consulta()->comStatus(StatusJogo::EmAndamento)->orderBy('data')->first()
            ?? $consulta()->comStatus(StatusJogo::Agendado)
                ->whereDate('data', '>=', today())
                ->orderBy('data')
                ->orderBy('horario')
                ->first();
    }

    /** @return array{total: int, ativos: int} */
    public function jogadores(): array
    {
        return [
            'total' => Usuario::query()->count(),
            'ativos' => Usuario::query()->ativos()->count(),
        ];
    }

    /** @return array{total: int, finalizados: int, hoje: int} */
    public function jogosNoMes(CarbonInterface $mes): array
    {
        $jogos = $this->doMes($mes)->get(['status', 'data']);

        return [
            'total' => $jogos->count(),
            'finalizados' => $jogos->where('status', StatusJogo::Finalizado)->count(),
            'hoje' => $jogos->filter(fn (Jogo $jogo): bool => $jogo->data->isToday())->count(),
        ];
    }

    /** @return array{ativas: int, total: int, inativas: list<string>} */
    public function mesas(): array
    {
        $mesas = Mesa::query()->orderBy('nome')->get(['nome', 'ativa']);

        return [
            'ativas' => $mesas->where('ativa', true)->count(),
            'total' => $mesas->count(),
            'inativas' => $mesas->where('ativa', false)->pluck('nome')->values()->all(),
        ];
    }

    /**
     * Arrecadação e prize pools dos jogos em andamento ou finalizados no mês, e aluguéis pagos no mês.
     *
     * @return array{arrecadacao: int, prizePools: int, jogos: int, alugueis: int, alugueisPagos: int}
     */
    public function financeiroDoMes(CarbonInterface $mes): array
    {
        $resumos = $this->doMes($mes)
            ->comStatus(StatusJogo::EmAndamento, StatusJogo::Finalizado)
            ->with('taxas', 'premiacoes')
            ->get()
            ->map(fn (Jogo $jogo) => $this->calculadora->doJogo($jogo));

        $pagos = Aluguel::query()
            ->where('status', StatusAluguel::Pago)
            ->whereHas('evento', fn (Builder $q) => self::noMes($q, $mes))
            ->get(['valor']);

        return [
            'arrecadacao' => $resumos->sum('arrecadacao'),
            'prizePools' => $resumos->sum('prizePool'),
            'jogos' => $resumos->count(),
            'alugueis' => $pagos->sum(fn (Aluguel $aluguel): int => Dinheiro::centavos($aluguel->valor)),
            'alugueisPagos' => $pagos->count(),
        ];
    }

    /**
     * Saldos em aberto dos jogadores em jogos finalizados (positivo: deve ao caixa; negativo: o caixa
     * deve) e aluguéis reservados ou confirmados cuja data já chegou.
     *
     * @return array{
     *     jogadores: Collection<int, array{jogo: Jogo, participante: Participante, saldo: int}>,
     *     alugueis: Collection<int, Aluguel>,
     *     aReceber: int,
     *     aPagar: int,
     * }
     */
    public function pendencias(): array
    {
        // Uma consulta só para todos os finalizados (o saldo vem do banco), em vez de duas por jogo.
        $comSaldo = Participante::query()
            ->whereHas('jogo', fn (Builder $q) => $q->reais()->comStatus(StatusJogo::Finalizado))
            ->comSaldo();

        $jogadores = Participante::query()->fromSub($comSaldo, 'participantes')
            ->where('saldo_em_aberto', '<>', 0)
            ->with(['usuario', 'jogo'])
            ->get()
            ->map(fn (Participante $participante): array => [
                'jogo' => $participante->jogo,
                'participante' => $participante,
                'saldo' => Dinheiro::centavos($participante->saldo_em_aberto),
            ])
            ->reject(fn (array $linha): bool => $linha['saldo'] === 0) // resto de float do SQLite
            ->sortBy(fn (array $linha): array => [$linha['jogo']->data, $linha['jogo']->id, $linha['participante']->id])
            ->values();

        $alugueis = Aluguel::query()
            ->whereIn('status', [StatusAluguel::Reservado, StatusAluguel::Confirmado])
            ->whereHas('evento', fn (Builder $q) => $q->whereDate('data', '<=', today()))
            ->with('evento')
            ->get()
            ->sortBy('evento.data')
            ->values();

        return [
            'jogadores' => $jogadores,
            'alugueis' => $alugueis,
            'aReceber' => $jogadores->where('saldo', '>', 0)->sum('saldo')
                + $alugueis->sum(fn (Aluguel $aluguel): int => Dinheiro::centavos($aluguel->valor)),
            'aPagar' => -$jogadores->where('saldo', '<', 0)->sum('saldo'),
        ];
    }

    /**
     * Os últimos jogos finalizados, do mais antigo para o mais recente.
     *
     * @return Collection<int, array{jogo: Jogo, rotulo: string, arrecadacao: int, prizePool: int, jogadores: int}>
     */
    public function ultimosJogos(int $quantos = 8): Collection
    {
        return Jogo::query()->reais()->comStatus(StatusJogo::Finalizado)
            ->with('taxas', 'premiacoes')
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->limit($quantos)
            ->get()
            ->reverse()
            ->map(function (Jogo $jogo): array {
                $resumo = $this->calculadora->doJogo($jogo);

                return [
                    'jogo' => $jogo,
                    'rotulo' => self::rotulo($jogo),
                    'arrecadacao' => $resumo->arrecadacao,
                    'prizePool' => $resumo->prizePool,
                    'jogadores' => $resumo->jogadores,
                ];
            })
            ->values();
    }

    /**
     * Jogos (agendados, em andamento e finalizados) por mês, terminando no mês dado.
     *
     * @return list<array{rotulo: string, total: int}>
     */
    public function jogosPorMes(CarbonInterface $ate, int $meses = 6): array
    {
        $ultimo = CarbonImmutable::parse($ate)->startOfMonth();

        return collect(range($meses - 1, 0))
            ->map(fn (int $atras): array => [
                'rotulo' => Str::ucfirst($ultimo->subMonthsNoOverflow($atras)->translatedFormat('M')),
                'total' => $this->doMes($ultimo->subMonthsNoOverflow($atras))->count(),
            ])
            ->all();
    }

    /** @return Collection<int, Evento> de hoje em diante (aluguel cancelado não entra) */
    public function proximosEventos(int $quantos = 7): Collection
    {
        return Evento::query()
            ->with('mesas', 'aluguel', 'jogo')
            ->whereDate('data', '>=', today())
            ->whereDoesntHave('aluguel', fn (Builder $q) => $q->where('status', StatusAluguel::Cancelado))
            ->orderBy('data')
            ->orderByRaw('hora_inicio is not null')
            ->orderBy('hora_inicio')
            ->limit($quantos)
            ->get();
    }

    /** "#18" de "Poker Night #18"; sem número, a data "04/10". */
    public static function rotulo(Jogo $jogo): string
    {
        return preg_match('/#\s*(\d+)/', $jogo->nome, $numero) ? '#'.$numero[1] : ($jogo->data?->format('d/m') ?? $jogo->nome);
    }

    private function doMes(CarbonInterface $mes): Builder
    {
        return self::noMes(Jogo::query()->reais()->comStatus(...self::NA_AGENDA), $mes);
    }

    /** Coluna `data` dentro do mês (whereDate: no SQLite a data vem com hora). */
    private static function noMes(Builder $query, CarbonInterface $mes): Builder
    {
        $inicio = CarbonImmutable::parse($mes)->startOfMonth();

        return $query->whereDate('data', '>=', $inicio)->whereDate('data', '<=', $inicio->endOfMonth());
    }
}
