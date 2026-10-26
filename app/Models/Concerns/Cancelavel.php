<?php

namespace App\Models\Concerns;

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

    /** Desfaz: marca quando, quem (`cancelada_por` / `cancelado_por`) e o motivo. Só pelo DesfazerAcao. */
    public function cancelar(Usuario $quem, string $motivo): void
    {
        $coluna = static::colunaCancelamento();

        $this->forceFill([
            $coluna => now(),
            str_replace('_em', '_por', $coluna) => $quem->id,
            'motivo_cancelamento' => $motivo,
        ])->save();
    }
}
