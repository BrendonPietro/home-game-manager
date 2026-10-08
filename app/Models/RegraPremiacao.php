<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Percentuais de premiação por faixa de jogadores (ex.: até 10 → 65/35). */
class RegraPremiacao extends Model
{
    protected $table = 'regras_premiacao';

    protected $fillable = [
        'jogadores_min',
        'jogadores_max',
        'percentuais',
    ];

    protected function casts(): array
    {
        return [
            'jogadores_min' => 'integer',
            'jogadores_max' => 'integer',
            'percentuais' => 'array',
        ];
    }

    public function scopeParaJogadores(Builder $query, int $jogadores): Builder
    {
        return $query->where('jogadores_min', '<=', $jogadores)
            ->where(fn (Builder $q) => $q->whereNull('jogadores_max')->orWhere('jogadores_max', '>=', $jogadores))
            ->orderByDesc('jogadores_min');
    }
}
