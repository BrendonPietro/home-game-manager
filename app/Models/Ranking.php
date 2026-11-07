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

    /** Ativos que valem hoje: o período começou (ou não tem início) e não acabou (ou não tem fim). */
    public function scopeValendoHoje(Builder $query): Builder
    {
        return $query->ativos()
            ->where(fn (Builder $q) => $q->whereNull('inicio')->orWhereDate('inicio', '<=', today()))
            ->where(fn (Builder $q) => $q->whereNull('fim')->orWhereDate('fim', '>=', today()));
    }

    /** Restringe uma consulta de `ranking_pontos` a este ranking e aos jogos dentro do período dele. */
    public function pontosDoPeriodo(Builder $pontos): Builder
    {
        return $pontos->where('ranking_id', $this->id)
            ->whereHas('jogo', fn (Builder $jogo) => $jogo
                ->when($this->inicio, fn (Builder $q) => $q->whereDate('data', '>=', $this->inicio))
                ->when($this->fim, fn (Builder $q) => $q->whereDate('data', '<=', $this->fim)));
    }

    /** Pontos já distribuídos no período. */
    public function totalDePontos(): int
    {
        return (int) $this->pontosDoPeriodo(RankingPonto::query())->sum('pontos');
    }

    /**
     * Quem pontuou, com `pontos`, `jogos`, `vitorias` e `melhor` (posição), só dos jogos dentro do período.
     * A ordem fica no `ordenarClassificacao`.
     */
    public function classificacao(): Builder
    {
        $noPeriodo = fn (Builder $pontos): Builder => $this->pontosDoPeriodo($pontos);

        return Usuario::query()->withTrashed()
            ->whereHas('pontosRanking', $noPeriodo)
            ->withSum(['pontosRanking as pontos' => $noPeriodo], 'pontos')
            ->withCount([
                'pontosRanking as jogos' => $noPeriodo,
                'pontosRanking as vitorias' => fn (Builder $q) => $noPeriodo($q)->where('posicao', 1),
            ])
            ->withMin(['pontosRanking as melhor' => $noPeriodo], 'posicao');
    }

    /** Mais pontos; no empate, mais vitórias, melhor posição, mais jogos e o nome. */
    public static function ordenarClassificacao(Builder $query): Builder
    {
        return $query->orderByDesc('pontos')->orderByDesc('vitorias')
            ->orderByRaw('melhor is null')->orderBy('melhor')->orderByDesc('jogos')->orderBy('nome');
    }
}
