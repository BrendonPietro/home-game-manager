<?php

namespace App\Models;

use App\Enums\Periodicidade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ranking (anual, mensal, semanal…). Só pontuam as posições em `pontuacoes`;
 * mesa final e participação são bônus opcionais.
 */
class Ranking extends Model
{
    protected $table = 'rankings';

    protected $fillable = [
        'nome',
        'periodicidade',
        'inicio',
        'fim',
        'ft_pontua',
        'pontos_mesa_final',
        'participacao_pontua',
        'pontos_participacao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'periodicidade' => Periodicidade::class,
            'inicio' => 'date',
            'fim' => 'date',
            'ft_pontua' => 'boolean',
            'pontos_mesa_final' => 'integer',
            'participacao_pontua' => 'boolean',
            'pontos_participacao' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function pontuacoes(): HasMany
    {
        return $this->hasMany(RankingPontuacao::class)->orderBy('posicao');
    }

    public function pontos(): HasMany
    {
        return $this->hasMany(RankingPonto::class);
    }

    public function jogos(): BelongsToMany
    {
        return $this->belongsToMany(Jogo::class, 'jogo_ranking');
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
