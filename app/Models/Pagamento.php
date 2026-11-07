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
        'acerto_id', // pagamento gerado por um acerto feito (desfaz junto com ele)
        'direcao',
        'valor',
        'forma',
        'observacao',
        'registrado_por',
        'registrado_no_painel_por', // pagamento lançado pelo painel (pré-torneio)
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

    /** Acerto feito que gerou este pagamento (nulo nos lançados à mão). */
    public function acerto(): BelongsTo
    {
        return $this->belongsTo(Acerto::class);
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cancelado_por')->withTrashed();
    }
}
