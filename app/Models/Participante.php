<?php

namespace App\Models;

use App\Enums\StatusParticipante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A pessoa naquele jogo: lugar, status, posição, prêmio ou cash-out. */
class Participante extends Model
{
    protected $table = 'participantes';

    protected $fillable = [
        'jogo_id',
        'usuario_id',
        'jogo_mesa_id',
        'lugar',
    ];

    protected $attributes = [
        'status' => 'confirmado',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusParticipante::class,
            'lugar' => 'integer',
            'posicao_final' => 'integer',
            'premio' => 'decimal:2',
            'fichas_finais' => 'integer',
            'cash_out' => 'decimal:2',
        ];
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class)->withTrashed();
    }

    public function jogoMesa(): BelongsTo
    {
        return $this->belongsTo(JogoMesa::class);
    }

    public function entradas(): HasMany
    {
        return $this->hasMany(Entrada::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }

    public function eliminacoes(): HasMany
    {
        return $this->hasMany(Eliminacao::class);
    }

    public function scopeNaMesa(Builder $query): Builder
    {
        return $query->where('status', StatusParticipante::Jogando);
    }
}
