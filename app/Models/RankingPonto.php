<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pontos que um usuário ganhou num ranking por um jogo. Gravado ao finalizar. */
class RankingPonto extends Model
{
    protected $table = 'ranking_pontos';

    protected $fillable = [
        'ranking_id',
        'jogo_id',
        'usuario_id',
        'posicao',
        'pontos',
        'detalhe',
    ];

    protected function casts(): array
    {
        return [
            'posicao' => 'integer',
            'pontos' => 'integer',
            'detalhe' => 'array',
        ];
    }

    public function ranking(): BelongsTo
    {
        return $this->belongsTo(Ranking::class);
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
