<?php

namespace App\Models;

use App\Enums\TipoItem;
use App\Models\Concerns\Cancelavel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item comprado por um jogador. Valor, fichas e o percentual da premiação copiados do item na hora.
 * Gravada só por App\Actions\Jogo\RegistrarEntrada; nunca apagada.
 */
class Entrada extends Model
{
    use Cancelavel;

    protected $table = 'entradas';

    protected $fillable = [
        'uuid',
        'jogo_id',
        'participante_id',
        'jogo_item_id',
        'tipo',
        'valor',
        'fichas',
        'percentual_premiacao',
        'vinculada_a',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoItem::class,
            'percentual_premiacao' => 'decimal:2',
            'valor' => 'decimal:2',
            'fichas' => 'integer',
            'cancelada_em' => 'datetime',
        ];
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class);
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'jogo_item_id');
    }

    /** Entrada (buy-in/rebuy/add-on) com que este opcional foi comprado. */
    public function vinculadaA(): BelongsTo
    {
        return $this->belongsTo(Entrada::class, 'vinculada_a');
    }

    /** Opcionais comprados junto desta entrada. */
    public function opcionais(): HasMany
    {
        return $this->hasMany(Entrada::class, 'vinculada_a');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por')->withTrashed();
    }

    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cancelada_por')->withTrashed();
    }
}
