<?php

namespace App\Support;

use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Models\Jogo;
use Illuminate\Support\Facades\Route;

/**
 * As etapas da noite no site (o "fluxo da noite" do protótipo) e qual delas cada status permite.
 * O middleware jogo.etapa e o botão Abrir usam isto para mandar cada um para a etapa certa.
 */
class EtapaDoJogo
{
    /** etapa => [rótulo, grupo] na ordem do fluxo */
    public const ETAPAS = [
        'conferencia' => ['Conferência', 'Antes'],
        'jogadores' => ['Jogadores', 'Antes'],
        'mesas' => ['Mesas', 'Antes'],
        'ao-vivo' => ['Ao vivo', 'Durante'],
        'resumo' => ['Resumo', 'Durante'],
        'premiacao' => ['Premiação', 'Final'],
        'resultado' => ['Resultado', 'Final'],
        'fechamento' => ['Fechamento', 'Final'],
    ];

    /** Status que aparecem no site (rascunho e cancelado ficam só no painel). */
    public const STATUS_NO_SITE = [StatusJogo::Agendado, StatusJogo::EmAndamento, StatusJogo::Finalizado];

    /** @return list<string> etapas do fluxo deste jogo (cash game não tem premiação) */
    public static function doJogo(Jogo $jogo): array
    {
        return array_values(array_filter(
            array_keys(self::ETAPAS),
            fn (string $etapa): bool => $etapa !== 'premiacao' || $jogo->eTorneio(),
        ));
    }

    /**
     * Etapas que o status atual deixa abrir. As finais (premiação, resultado, fechamento) abrem com
     * as entradas fechadas no torneio, e a qualquer hora no cash game; o Finalizar fica no fechamento.
     *
     * @return list<string>
     */
    public static function permitidas(Jogo $jogo): array
    {
        $etapas = match ($jogo->status) {
            StatusJogo::Agendado => ['conferencia', 'jogadores', 'mesas'],
            StatusJogo::EmAndamento => self::finaisAbertas($jogo)
                ? ['ao-vivo', 'resumo', 'premiacao', 'resultado', 'fechamento']
                : ['ao-vivo', 'resumo'],
            StatusJogo::Finalizado => ['premiacao', 'resultado', 'fechamento'],
            default => [],
        };

        return array_values(array_intersect(self::doJogo($jogo), $etapas));
    }

    public static function permite(Jogo $jogo, string $etapa): bool
    {
        return in_array($etapa, self::permitidas($jogo), true);
    }

    /** Em andamento, as etapas finais já abrem? (entradas fechadas no torneio; sempre no cash game) */
    public static function finaisAbertas(Jogo $jogo): bool
    {
        return $jogo->eCashGame() || $jogo->fase === FaseTorneio::SemEntradas;
    }

    /** Onde o jogo está agora: a primeira etapa que o status permite. */
    public static function atual(Jogo $jogo): ?string
    {
        return match ($jogo->status) {
            StatusJogo::Agendado => 'conferencia',
            StatusJogo::EmAndamento => 'ao-vivo',
            StatusJogo::Finalizado => 'resultado',
            default => null,
        };
    }

    /** URL da etapa atual, ou null enquanto a tela dela não existe (fases seguintes). */
    public static function url(Jogo $jogo, ?string $etapa = null): ?string
    {
        $etapa ??= self::atual($jogo);

        if ($etapa === null || ! Route::has('jogos.'.$etapa)) {
            return null;
        }

        return route('jogos.'.$etapa, $jogo);
    }
}
