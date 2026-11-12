<?php

namespace App\Models;

use App\Enums\TipoItem;
use App\Support\Dinheiro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Linha de jogo_itens: uma entrada (buy-in, rebuy, add-on e variações) ou um opcional.
 * `compravel_com`: ["buyin","rebuy","addon"] ou ["jogador"] (só opcional).
 * `percentual_premiacao`: quanto do valor vai para a premiação, de 0 a 100 (buy-in 100%, churrasco 0%…).
 * `pago_antes` (só opcional): dá para marcar quem pagou antes (taxa da pizza…), e ele entra junto do buy-in.
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
        'percentual_premiacao',
        'compravel_com',
        'obrigatorio',
        'pago_antes',
        'limite_por_jogador',
        'padrao',
        'ativo',
        'ordem',
    ];

    protected $attributes = [
        'percentual_premiacao' => 100,
        'fichas' => 0,
        'obrigatorio' => false,
        'pago_antes' => false,
        'padrao' => false,
        'ativo' => true,
        'ordem' => 0,
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoItem::class,
            'percentual_premiacao' => 'decimal:2',
            'valor' => 'decimal:2',
            'fichas' => 'integer',
            'compravel_com' => 'array',
            'obrigatorio' => 'boolean',
            'pago_antes' => 'boolean',
            'limite_por_jogador' => 'integer',
            'padrao' => 'boolean',
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Entrada não vai junto de outra nem é obrigatória ou paga antes (isso é dos opcionais).
        static::saving(function (Item $item) {
            if ($item->tipo instanceof TipoItem && $item->tipo->eEntrada()) {
                $item->compravel_com = null;
                $item->obrigatorio = false;
                $item->pago_antes = false;
            }
        });
    }

    /** "vai para a premiação" (100%), "75% na premiação", "não vai para a premiação" (0%). */
    public function textoDaPremiacao(): string
    {
        $centesimos = Dinheiro::centavos($this->percentual_premiacao);

        return match (true) {
            $centesimos >= 10000 => 'vai para a premiação',
            $centesimos <= 0 => 'não vai para a premiação',
            default => str_replace('.', ',', rtrim(rtrim(Dinheiro::decimal($centesimos), '0'), '.')).'% na premiação',
        };
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class);
    }

    public function entradas(): HasMany
    {
        return $this->hasMany(Entrada::class, 'jogo_item_id');
    }

    /** Quem pagou este opcional antes (Participante::opcionaisPagos, do outro lado). */
    public function pagaramAntes(): BelongsToMany
    {
        return $this->belongsToMany(Participante::class, 'participante_opcionais', 'jogo_item_id', 'participante_id')->withTimestamps();
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
