<?php

namespace App\Models;

use App\Enums\DirecaoPagamento;
use App\Enums\Modalidade;
use App\Enums\StatusParticipante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
            'pagou_antes' => 'boolean',
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

    /** Opcionais que ele pagou antes (taxa da pizza…): entram junto do buy-in (Actions\Jogo\MarcarOpcionalPagoAntes). */
    public function opcionaisPagos(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'participante_opcionais', 'participante_id', 'jogo_item_id')->withTimestamps();
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

    /**
     * Acrescenta `saldo_em_aberto` (decimal): investido − prêmio (cash-out no cash game) − pago + recebido.
     * É a conta do Services\Jogo\Saldos feita no banco, para listar pendências de muitos jogos numa
     * consulta só (o dashboard do painel). Mudou a conta lá? Mude aqui e no Usuario::scopeComSaldoEmAberto.
     */
    public function scopeComSaldo(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('participantes.*'); // selectRaw não põe o "*" sozinho
        }

        return $query->selectRaw(
            '(select coalesce(sum(e.valor), 0) from entradas e where e.participante_id = participantes.id and e.cancelada_em is null)
            - (select case when j.modalidade = ? then coalesce(participantes.cash_out, 0) else coalesce(participantes.premio, 0) end
                from jogos j where j.id = participantes.jogo_id)
            - (select coalesce(sum(pg.valor), 0) from pagamentos pg where pg.participante_id = participantes.id and pg.cancelado_em is null and pg.direcao = ?)
            + (select coalesce(sum(pg.valor), 0) from pagamentos pg where pg.participante_id = participantes.id and pg.cancelado_em is null and pg.direcao = ?)
            as saldo_em_aberto',
            [Modalidade::CashGame->value, DirecaoPagamento::JogadorPagou->value, DirecaoPagamento::JogadorRecebeu->value],
        );
    }

    /** Eliminado ou saiu (cash-out): o lugar continua dele até alguém sentar ali. */
    public function estaFora(): bool
    {
        return in_array($this->status, [StatusParticipante::Eliminado, StatusParticipante::Saiu], true);
    }

    public function nome(): string
    {
        return $this->usuario->nome_exibicao;
    }
}
