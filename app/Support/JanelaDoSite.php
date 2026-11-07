<?php

namespace App\Support;

use App\Enums\StatusJogo;
use App\Models\Jogo;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Quando o site abre para quem não é admin (sem `site.fora_da_noite`, o Operador): de 3 h antes do início
 * do jogo até 3 h depois do término previsto (`jogos.horario_fim`; antes do início = no dia seguinte).
 * Sem término previsto, o término conta como 12 h depois do início. Jogo em andamento fica aberto até
 * finalizar, mesmo passando da hora. Fora disso, quem já entrou continua logado, mas só vê "Nenhum torneio agora".
 */
class JanelaDoSite
{
    public const HORAS_ANTES = 3;

    public const HORAS_DEPOIS = 3;

    /** Duração que se considera quando o jogo não tem término previsto. */
    public const HORAS_SEM_TERMINO = 12;

    /** Data e horário do início (sem horário, o começo do dia). */
    public static function inicio(Jogo $jogo): CarbonImmutable
    {
        $hora = Datas::hora($jogo->horario) ?? '00:00';

        return CarbonImmutable::parse($jogo->data->format('Y-m-d').' '.$hora);
    }

    /** Término previsto: o horário de fim (no dia seguinte se for antes do início) ou 12 h depois do início. */
    public static function termino(Jogo $jogo): CarbonImmutable
    {
        $inicio = self::inicio($jogo);

        if (! $jogo->horario_fim) {
            return $inicio->addHours(self::HORAS_SEM_TERMINO);
        }

        $fim = CarbonImmutable::parse($jogo->data->format('Y-m-d').' '.Datas::hora($jogo->horario_fim));

        return $fim->lte($inicio) ? $fim->addDay() : $fim;
    }

    public static function abre(Jogo $jogo): CarbonImmutable
    {
        return self::inicio($jogo)->subHours(self::HORAS_ANTES);
    }

    public static function fecha(Jogo $jogo): CarbonImmutable
    {
        return self::termino($jogo)->addHours(self::HORAS_DEPOIS);
    }

    public static function aberta(Jogo $jogo, ?CarbonInterface $agora = null): bool
    {
        if ($jogo->status === StatusJogo::EmAndamento) {
            return true;
        }

        if (! in_array($jogo->status, [StatusJogo::Agendado, StatusJogo::Finalizado], true) || $jogo->data === null || $jogo->e_modelo) {
            return false;
        }

        $agora ??= now();

        return $agora->gte(self::abre($jogo)) && $agora->lte(self::fecha($jogo));
    }

    /**
     * Os jogos que o site mostra agora para quem não é admin: os em andamento e os agendados ou
     * finalizados dentro da janela, na ordem do horário.
     *
     * @return Collection<int, Jogo>
     */
    public static function jogosAbertos(?CarbonInterface $agora = null): Collection
    {
        $agora ??= now();
        $hoje = CarbonImmutable::parse($agora)->startOfDay();

        // A janela vai, no máximo, da véspera (início à meia-noite − 3 h) a dois dias depois (fim na manhã seguinte + 3 h).
        return Jogo::query()->reais()
            ->where(fn (Builder $q) => $q->where('status', StatusJogo::EmAndamento)
                ->orWhere(fn (Builder $q) => $q->comStatus(StatusJogo::Agendado, StatusJogo::Finalizado)
                    ->whereDate('data', '>=', $hoje->subDays(2))
                    ->whereDate('data', '<=', $hoje->addDay())))
            ->orderBy('data')->orderBy('horario')->orderBy('id')
            ->get()
            ->filter(fn (Jogo $jogo): bool => self::aberta($jogo, $agora))
            ->values();
    }

    /** O próximo jogo agendado cuja janela ainda não abriu (para dizer quando o site abre). */
    public static function proximo(?CarbonInterface $agora = null): ?Jogo
    {
        $agora ??= now();

        return Jogo::query()->reais()->comStatus(StatusJogo::Agendado)
            ->whereDate('data', '>=', CarbonImmutable::parse($agora)->startOfDay()->subDay())
            ->orderBy('data')->orderBy('horario')->orderBy('id')
            ->get()
            ->first(fn (Jogo $jogo): bool => self::abre($jogo)->gt($agora));
    }
}
