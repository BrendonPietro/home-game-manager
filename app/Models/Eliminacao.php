<?php

namespace App\Models;

use App\Models\Concerns\Cancelavel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eliminação provisória (fase rebuys) ou definitiva (depois). A provisória fica como histórico quando
 * o jogador volta com rebuy (`voltou_com`); desfazer marca `cancelada_em`.
 */
class Eliminacao extends Model
{
    use Cancelavel;

    protected $table = 'eliminacoes';

    protected $fillable = [
        'jogo_id',
        'participante_id',
        'nivel',
        'definitiva',
        'voltou_com',
        'registrada_por',
    ];

    protected function casts(): array
    {
        return [
            'nivel' => 'integer',
            'definitiva' => 'boolean',
            'cancelada_em' => 'datetime',
        ];
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class);
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    /** Rebuy com que o jogador voltou (só eliminação provisória). */
    public function voltouCom(): BelongsTo
    {
        return $this->belongsTo(Entrada::class, 'voltou_com');
    }

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrada_por')->withTrashed();
    }

    public function scopeDefinitivas(Builder $query): Builder
    {
        return $query->where('definitiva', true);
    }

    /** Provisórias de quem ainda está fora (não voltou com rebuy). */
    public function scopeEmAberto(Builder $query): Builder
    {
        return $query->where('definitiva', false)->whereNull('voltou_com');
    }
}
