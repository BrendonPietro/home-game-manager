<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mesa do jogo ("Mesa 1", "Mesa 2"…), com os lugares e o dealer dela. Não depende do cadastro de mesas,
 * que fica para aluguéis e reservas: o torneio pode ter mais mesas do que as cadastradas.
 */
class JogoMesa extends Model
{
    protected $table = 'jogo_mesas';

    protected $fillable = [
        'jogo_id',
        'nome',
        'dealer_id',
        'lugares',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'lugares' => 'integer',
            'ordem' => 'integer',
        ];
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class);
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'dealer_id');
    }

    public function participantes(): HasMany
    {
        return $this->hasMany(Participante::class)->orderBy('lugar');
    }
}
