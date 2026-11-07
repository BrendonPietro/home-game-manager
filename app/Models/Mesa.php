<?php

namespace App\Models;

use Database\Factories\MesaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Mesa física do clube, para aluguéis, reservas e bloqueios da agenda (o torneio tem as mesas dele: JogoMesa). */
class Mesa extends Model
{
    /** @use HasFactory<MesaFactory> */
    use HasFactory;

    protected $table = 'mesas';

    protected $fillable = [
        'nome',
        'lugares',
        'valor_aluguel',
        'ativa',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'lugares' => 'integer',
            'valor_aluguel' => 'decimal:2',
            'ativa' => 'boolean',
        ];
    }

    public function eventos(): BelongsToMany
    {
        return $this->belongsToMany(Evento::class, 'evento_mesa');
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativa', true);
    }
}
