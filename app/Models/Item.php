<?php

namespace App\Models;

use App\Enums\DestinoItem;
use App\Enums\TipoItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Linha de jogo_itens: uma entrada (buy-in, rebuy, add-on e variações) ou um opcional.
 * `compravel_com`: ["buyin","rebuy","addon"] ou ["jogador"] (só opcional).
 */
class Item extends Model
{
    public const COMPRAVEL_POR_JOGADOR = 'jogador';

    protected $table = 'jogo_itens';

    protected $fillable = [
        'jogo_id',
        'tipo',
        'nome',
        'valor',
        'fichas',
        'destino',
        'compravel_com',
        'obrigatorio',
        'limite_por_jogador',
        'padrao',
        'ativo',
        'ordem',
    ];

    protected $attributes = [
        'destino' => 'premiacao',
        'fichas' => 0,
        'obrigatorio' => false,
        'padrao' => false,
        'ativo' => true,
        'ordem' => 0,
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoItem::class,
            'destino' => DestinoItem::class,
            'valor' => 'decimal:2',
            'fichas' => 'integer',
            'compravel_com' => 'array',
            'obrigatorio' => 'boolean',
            'limite_por_jogador' => 'integer',
            'padrao' => 'boolean',
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Entradas vão sempre 100% para a premiação.
        static::saving(function (Item $item) {
            if ($item->tipo instanceof TipoItem && $item->tipo->eEntrada()) {
                $item->destino = DestinoItem::Premiacao;
                $item->compravel_com = null;
                $item->obrigatorio = false;
            }
        });
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class);
    }

    public function entradas(): HasMany
    {
        return $this->hasMany(Entrada::class, 'jogo_item_id');
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function scopeDeEntrada(Builder $query): Builder
    {
        return $query->whereIn('tipo', TipoItem::entradas());
    }

    public function scopeOpcionais(Builder $query): Builder
    {
        return $query->where('tipo', TipoItem::Opcional);
    }

    /** Opcional que aparece como checkbox ao lançar uma entrada desse tipo. */
    public function compravelCom(TipoItem $tipo): bool
    {
        return in_array($tipo->value, $this->compravel_com ?? [], true);
    }

    public function porJogador(): bool
    {
        return in_array(self::COMPRAVEL_POR_JOGADOR, $this->compravel_com ?? [], true);
    }
}
