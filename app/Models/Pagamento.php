<?php

namespace App\Models;

use App\Enums\DirecaoPagamento;
use App\Enums\FormaPagamento;
use App\Models\Concerns\Cancelavel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pagamento parcial ou total entre jogador e caixa. Nunca apagado. */
class Pagamento extends Model
{
    use Cancelavel;

    public const COLUNA_CANCELAMENTO = 'cancelado_em';

    protected $table = 'pagamentos';

    protected $fillable = [
        'uuid',
        'jogo_id',
        'participante_id',
        'direcao',
        'valor',
        'forma',
        'observacao',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'direcao' => DirecaoPagamento::class,
            'forma' => FormaPagamento::class,
            'valor' => 'decimal:2',
            'cancelado_em' => 'datetime',
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

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por')->withTrashed();
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cancelado_por')->withTrashed();
    }
}
