<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mesa usada num jogo, com o dealer dela. */
class JogoMesa extends Model
{
    protected $table = 'jogo_mesas';

    protected $fillable = [
        'jogo_id',
        'mesa_id',
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

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
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
