<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Quem paga quem" sugerido no fechamento. Participante nulo = o caixa. */
class Acerto extends Model
{
    protected $table = 'acertos';

    protected $fillable = [
        'jogo_id',
        'de_participante_id',
        'para_participante_id',
        'valor',
        'pago',
        'pago_em',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'pago' => 'boolean',
            'pago_em' => 'datetime',
        ];
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class);
    }

    public function de(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'de_participante_id');
    }

    public function para(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'para_participante_id');
    }
}
