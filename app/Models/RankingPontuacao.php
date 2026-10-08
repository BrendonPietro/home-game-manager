<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pontos de uma posição num ranking (só pontua quem está na lista). */
class RankingPontuacao extends Model
{
    protected $table = 'ranking_pontuacoes';

    protected $fillable = [
        'ranking_id',
        'posicao',
        'pontos',
    ];

    protected function casts(): array
    {
        return [
            'posicao' => 'integer',
            'pontos' => 'integer',
        ];
    }

    public function ranking(): BelongsTo
    {
        return $this->belongsTo(Ranking::class);
    }
}
