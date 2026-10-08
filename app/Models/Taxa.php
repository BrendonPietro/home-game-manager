<?php

namespace App\Models;

use App\Enums\CalculoTaxa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Taxa tirada da premiação antes de dividir (dealers, casa…). */
class Taxa extends Model
{
    protected $table = 'jogo_taxas';

    protected $fillable = [
        'jogo_id',
        'nome',
        'calculo',
        'valor',
        'quantidade',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'calculo' => CalculoTaxa::class,
            'valor' => 'decimal:2',
            'quantidade' => 'integer',
            'ordem' => 'integer',
        ];
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class);
    }
}
