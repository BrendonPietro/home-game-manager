<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Percentual de uma posição no torneio; `valor` é gravado ao fechar entradas. */
class Premiacao extends Model
{
    protected $table = 'jogo_premiacoes';

    protected $fillable = [
        'jogo_id',
        'posicao',
        'percentual',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'posicao' => 'integer',
            'percentual' => 'decimal:2',
            'valor' => 'decimal:2',
        ];
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class);
    }
}
