<?php

namespace App\Models;

use App\Enums\StatusAluguel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aluguel extends Model
{
    protected $table = 'alugueis';

    protected $fillable = [
        'evento_id',
        'cliente',
        'valor',
        'status',
        'pago_em',
    ];

    protected $attributes = [
        'status' => 'reservado',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusAluguel::class,
            'valor' => 'decimal:2',
            'pago_em' => 'datetime',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    /** Reservado ou confirmado: ainda dá para marcar pago. */
    public function emAberto(): bool
    {
        return in_array($this->status, [StatusAluguel::Reservado, StatusAluguel::Confirmado], true);
    }
}
