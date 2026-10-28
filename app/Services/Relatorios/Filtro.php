<?php

namespace App\Services\Relatorios;

use App\Models\Temporada;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Filtros da tela de relatórios (tela 24): período (pela data do jogo ou do aluguel), jogo e jogador.
 * Sem início ou sem fim, o período fica aberto daquele lado.
 */
final readonly class Filtro
{
    public function __construct(
        public ?CarbonImmutable $de = null,
        public ?CarbonImmutable $ate = null,
        public ?int $jogoId = null,
        public ?int $usuarioId = null,
        public string $periodo = 'Tudo',
    ) {}

    /** Períodos prontos (as temporadas entram como "temporada:{id}"). */
    public static function opcoesDePeriodo(): array
    {
        $temporadas = Temporada::query()->orderByDesc('inicio')->get()
            ->mapWithKeys(fn (Temporada $t): array => ['temporada:'.$t->id => 'Temporada '.$t->nome])
            ->all();

        return $temporadas + [
            'mes' => 'Este mês',
            'mes_anterior' => 'Mês passado',
            '30_dias' => 'Últimos 30 dias',
            'ano' => 'Este ano',
            'tudo' => 'Tudo',
            'personalizado' => 'Personalizado',
        ];
    }

    /** Temporada de hoje, se houver; senão o ano. */
    public static function periodoPadrao(): string
    {
        $atual = Temporada::query()
            ->whereDate('inicio', '<=', today())
            ->where(fn ($q) => $q->whereNull('fim')->orWhereDate('fim', '>=', today()))
            ->orderByDesc('inicio')
            ->first();

        return $atual ? 'temporada:'.$atual->id : 'ano';
    }

    /** @param  array{periodo?: ?string, de?: ?string, ate?: ?string, jogo?: int|string|null, jogador?: int|string|null}  $campos */
    public static function doFormulario(array $campos): self
    {
        $hoje = CarbonImmutable::today();
        $periodo = $campos['periodo'] ?? 'tudo';
        $data = fn (?string $valor): ?CarbonImmutable => filled($valor) ? CarbonImmutable::parse($valor) : null;

        [$de, $ate, $texto] = match (true) {
            str_starts_with($periodo, 'temporada:') => self::temporada((int) Str::after($periodo, ':')),
            $periodo === 'mes' => [$hoje->startOfMonth(), $hoje->endOfMonth(), Str::ucfirst($hoje->translatedFormat('F Y'))],
            $periodo === 'mes_anterior' => [
                $hoje->subMonthNoOverflow()->startOfMonth(),
                $hoje->subMonthNoOverflow()->endOfMonth(),
                Str::ucfirst($hoje->subMonthNoOverflow()->translatedFormat('F Y')),
            ],
            $periodo === '30_dias' => [$hoje->subDays(29), $hoje, 'Últimos 30 dias'],
            $periodo === 'ano' => [$hoje->startOfYear(), $hoje->endOfYear(), (string) $hoje->year],
            $periodo === 'personalizado' => [$data($campos['de'] ?? null), $data($campos['ate'] ?? null), null],
            default => [null, null, 'Tudo'],
        };

        $texto ??= match (true) {
            $de && $ate => $de->format('d/m/Y').' a '.$ate->format('d/m/Y'),
            $de !== null => 'Desde '.$de->format('d/m/Y'),
            $ate !== null => 'Até '.$ate->format('d/m/Y'),
            default => 'Tudo',
        };

        return new self(
            de: $de,
            ate: $ate,
            jogoId: filled($campos['jogo'] ?? null) ? (int) $campos['jogo'] : null,
            usuarioId: filled($campos['jogador'] ?? null) ? (int) $campos['jogador'] : null,
            periodo: $texto,
        );
    }

    /** @return array{?CarbonImmutable, ?CarbonImmutable, string} */
    private static function temporada(int $id): array
    {
        $temporada = Temporada::query()->find($id);

        return $temporada
            ? [$temporada->inicio?->toImmutable(), $temporada->fim?->toImmutable(), 'Temporada '.$temporada->nome]
            : [null, null, 'Tudo'];
    }
}
