<?php

namespace App\Models;

use App\Models\Concerns\Cancelavel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eliminação provisória (fase rebuys) ou definitiva (depois do intervalo). */
class Eliminacao extends Model
{
    use Cancelavel;

    protected $table = 'eliminacoes';

    protected $fillable = [
        'jogo_id',
        'participante_id',
        'nivel',
        'definitiva',
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

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrada_por')->withTrashed();
    }

    public function scopeDefinitivas(Builder $query): Builder
    {
        return $query->where('definitiva', true);
    }
}
