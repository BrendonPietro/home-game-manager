<?php

namespace App\Models;

use App\Enums\FaseTorneio;
use App\Enums\MetodoPremiacao;
use App\Enums\Modalidade;
use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Torneio ou cash game. Entradas, pagamentos, eliminações e mudança de status
 * passam sempre por App\Actions\Jogo\*, nunca direto pelo model.
 */
class Jogo extends Model
{
    /** @use HasFactory<\Database\Factories\JogoFactory> */
    use HasFactory;

    protected $table = 'jogos';

    protected $fillable = [
        'e_modelo',
        'nome_modelo',
        'modalidade',
        'temporada_id',
        'nome',
        'data',
        'horario',
        'floor_id',
        'metodo_premiacao',
        'lugares_por_mesa',
        'tamanho_mesa_final',
        'nivel_atual',
        'blinds_atual',
        'valor_ficha',
        'observacoes',
    ];

    // status, fase e datas de etapa ficam fora do fillable: só mudam pelas Actions.

    protected $attributes = [
        'e_modelo' => false,
        'modalidade' => 'torneio',
        'status' => 'rascunho',
        'metodo_premiacao' => 'regra',
        'lugares_por_mesa' => 9,
        'tamanho_mesa_final' => 9,
    ];

    protected function casts(): array
    {
        return [
            'e_modelo' => 'boolean',
            'modalidade' => Modalidade::class,
            'status' => StatusJogo::class,
            'fase' => FaseTorneio::class,
            'metodo_premiacao' => MetodoPremiacao::class,
            'data' => 'date',
            'lugares_por_mesa' => 'integer',
            'tamanho_mesa_final' => 'integer',
            'nivel_atual' => 'integer',
            'valor_ficha' => 'decimal:4',
            'iniciado_em' => 'datetime',
            'rebuys_encerrados_em' => 'datetime',
            'entradas_fechadas_em' => 'datetime',
            'finalizado_em' => 'datetime',
        ];
    }

    // ── Relações ─────────────────────────────────────────────

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'floor_id');
    }

    /** Entradas e opcionais configurados (jogo_itens). */
    public function itens(): HasMany
    {
        return $this->hasMany(Item::class)->orderBy('ordem')->orderBy('id');
    }

    /** Só buy-in, rebuy e add-on (com variações). */
    public function itensDeEntrada(): HasMany
    {
        return $this->itens()->whereIn('tipo', TipoItem::entradas());
    }

    public function opcionais(): HasMany
    {
        return $this->itens()->where('tipo', TipoItem::Opcional);
    }

    public function taxas(): HasMany
    {
        return $this->hasMany(Taxa::class)->orderBy('ordem')->orderBy('id');
    }

    public function premiacoes(): HasMany
    {
        return $this->hasMany(Premiacao::class)->orderBy('posicao');
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(JogoMesa::class)->orderBy('ordem');
    }

    public function rankings(): BelongsToMany
    {
        return $this->belongsToMany(Ranking::class, 'jogo_ranking');
    }

    public function participantes(): HasMany
    {
        return $this->hasMany(Participante::class);
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

    public function acertos(): HasMany
    {
        return $this->hasMany(Acerto::class);
    }

    public function pontosRanking(): HasMany
    {
        return $this->hasMany(RankingPonto::class);
    }

    public function evento(): HasOne
    {
        return $this->hasOne(Evento::class);
    }

    // ── Escopos ─────────────────────────────────────────────

    /** Jogos de verdade (exclui torneios modelo). Use em tudo que o site e o calendário listam. */
    public function scopeReais(Builder $query): Builder
    {
        return $query->where('e_modelo', false);
    }

    public function scopeModelos(Builder $query): Builder
    {
        return $query->where('e_modelo', true);
    }

    public function scopeComStatus(Builder $query, StatusJogo ...$status): Builder
    {
        return $query->whereIn('status', $status);
    }

    // ── Estado ─────────────────────────────────────────────

    public function eTorneio(): bool
    {
        return $this->modalidade === Modalidade::Torneio;
    }

    public function eCashGame(): bool
    {
        return $this->modalidade === Modalidade::CashGame;
    }

    public function estaFinalizado(): bool
    {
        return $this->status === StatusJogo::Finalizado;
    }

    public function estaNaFase(FaseTorneio $fase): bool
    {
        return $this->status === StatusJogo::EmAndamento && $this->fase === $fase;
    }

    /** Admin do site ainda pode mexer em entradas, opcionais e taxas. */
    public function estruturaEditavel(): bool
    {
        return ! $this->estaFinalizado() && $this->entradas_fechadas_em === null;
    }
}
