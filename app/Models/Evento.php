<?php

namespace App\Models;

use App\Enums\TipoEvento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Item do calendário: jogo, aluguel, reserva ou bloqueio. */
class Evento extends Model
{
    protected $table = 'eventos';

    protected $fillable = [
        'tipo',
        'nome',
        'data',
        'hora_inicio',
        'hora_fim',
        'responsavel_id',
        'jogo_id',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoEvento::class,
            'data' => 'date',
        ];
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsavel_id')->withTrashed();
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class);
    }

    public function mesas(): BelongsToMany
    {
        return $this->belongsToMany(Mesa::class, 'evento_mesa');
    }

    public function aluguel(): HasOne
    {
        return $this->hasOne(Aluguel::class);
    }
}
