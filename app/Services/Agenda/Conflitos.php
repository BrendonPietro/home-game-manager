<?php

namespace App\Services\Agenda;

use App\Enums\StatusAluguel;
use App\Models\Evento;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Eventos do calendário que disputam a mesma mesa no mesmo horário. Só avisa: quem cadastra decide.
 *
 * Horário: sem início = dia todo; sem fim = até o fim do dia; fim antes do início = passa da meia-noite
 * (20:00–01:00). Aluguel cancelado não ocupa mesa.
 */
class Conflitos
{
    private const DIA = 24 * 60;

    /**
     * @param  list<int|string>  $mesas
     * @return Collection<int, Evento>
     */
    public function de(CarbonInterface|string $data, ?string $inicio, ?string $fim, array $mesas, ?int $ignorar = null): Collection
    {
        if ($mesas === []) {
            return collect();
        }

        $janela = self::janela($inicio, $fim);

        return Evento::query()
            ->with('mesas')
            ->whereDate('data', $data)
            ->when($ignorar, fn (Builder $q) => $q->whereKeyNot($ignorar))
            ->whereHas('mesas', fn (Builder $q) => $q->whereKey($mesas))
            ->whereDoesntHave('aluguel', fn (Builder $q) => $q->where('status', StatusAluguel::Cancelado))
            ->orderBy('hora_inicio')
            ->get()
            ->filter(fn (Evento $evento): bool => self::cruzam($janela, self::janela($evento->hora_inicio, $evento->hora_fim)))
            ->values();
    }

    /** @return array{int, int} início e fim em minutos desde a meia-noite do dia */
    private static function janela(?string $inicio, ?string $fim): array
    {
        if (! $inicio) {
            return [0, self::DIA];
        }

        $de = self::minutos($inicio);
        $ate = $fim ? self::minutos($fim) : self::DIA;

        return [$de, $ate <= $de ? $ate + self::DIA : $ate];
    }

    private static function minutos(string $hora): int
    {
        [$h, $m] = array_map('intval', explode(':', $hora));

        return $h * 60 + $m;
    }

    /** @param array{int, int} $a @param array{int, int} $b */
    private static function cruzam(array $a, array $b): bool
    {
        return $a[0] < $b[1] && $b[0] < $a[1];
    }
}
