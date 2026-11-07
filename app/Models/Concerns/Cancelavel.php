<?php

namespace App\Models\Concerns;

use App\Models\Seguranca;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registros que nunca são apagados: desfazer marca a coluna de cancelamento.
 * O model define a constante COLUNA_CANCELAMENTO quando ela não é `cancelada_em`.
 */
trait Cancelavel
{
    public static function colunaCancelamento(): string
    {
        return defined(static::class.'::COLUNA_CANCELAMENTO') ? static::COLUNA_CANCELAMENTO : 'cancelada_em';
    }

    /** Só os registros que valem (não cancelados). */
    public function scopeValidas(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn(static::colunaCancelamento()));
    }

    public function scopeCanceladas(Builder $query): Builder
    {
        return $query->whereNotNull($query->qualifyColumn(static::colunaCancelamento()));
    }

    public function estaCancelada(): bool
    {
        return $this->getAttribute(static::colunaCancelamento()) !== null;
    }

    /**
     * Desfaz: marca quando, quem (`cancelada_por` / `cancelado_por`, do site) e o motivo. Só pelas Actions
     * de desfazer; pelo painel, quem fez fica no histórico do jogo (`alteracoes`).
     */
    public function cancelar(Usuario|Seguranca $quem, string $motivo): void
    {
        $coluna = static::colunaCancelamento();

        $this->forceFill([
            $coluna => now(),
            str_replace('_em', '_por', $coluna) => $quem instanceof Usuario ? $quem->id : null,
            'motivo_cancelamento' => $motivo,
        ])->save();
    }
}
