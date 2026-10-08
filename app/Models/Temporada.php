<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Temporada extends Model
{
    protected $table = 'temporadas';

    protected $fillable = [
        'nome',
        'inicio',
        'fim',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'fim' => 'date',
        ];
    }

    public function jogos(): HasMany
    {
        return $this->hasMany(Jogo::class);
    }
}
