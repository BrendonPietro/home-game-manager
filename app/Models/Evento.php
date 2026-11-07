<?php

namespace App\Models;

use App\Enums\TipoEvento;
use App\Support\Datas;
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

    /** "19:00–23:30", "20:00" ou "Dia todo" */
    public function horario(): string
    {
        $inicio = Datas::hora($this->hora_inicio);
        $fim = Datas::hora($this->hora_fim);

        return match (true) {
            $inicio === null => 'Dia todo',
            $fim === null => $inicio,
            default => "{$inicio}–{$fim}",
        };
    }

    /** "Mesa 01 + Mesa 02" ou "—". O jogo não ocupa mesas do cadastro: mostra as mesas do próprio jogo. */
    public function nomesDasMesas(): string
    {
        $nomes = $this->jogo_id
            ? ($this->jogo?->mesas->pluck('nome') ?? collect())
            : $this->mesas->sortBy('nome')->pluck('nome');

        return $nomes->join(' + ') ?: '—';
    }
}
