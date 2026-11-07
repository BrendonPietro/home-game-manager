<?php

namespace App\Models;

use App\Enums\FaseTorneio;
use App\Enums\MetodoPremiacao;
use App\Enums\Modalidade;
use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Support\Dinheiro;
use Database\Factories\JogoFactory;
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
    /** @use HasFactory<JogoFactory> */
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
        'estrutura_blinds_id',
        'nivel_atual',
        'blinds_atual',
        'valor_ficha',
        'observacoes',
    ];

    // status, fase, datas de etapa e o relógio (relogio_*) ficam fora do fillable: só mudam pelas Actions.

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
            'relogio_segundos' => 'integer',
            'relogio_desde' => 'datetime',
            'relogio_iniciado_em' => 'datetime',
            'avisos' => 'integer',
            'aviso_em' => 'datetime',
            'iniciado_em' => 'datetime',
            'intervalo_aberto_em' => 'datetime',
            'entradas_fechadas_em' => 'datetime',
            'finalizado_em' => 'datetime',
        ];
    }

    /** Cash game não tem estrutura de blinds (nem relógio): os blinds são digitados. */
    protected static function booted(): void
    {
        // v3: todo jogo nasce com o código do link do relógio (cópia e modelo ganham um novo).
        static::creating(function (Jogo $jogo): void {
            $jogo->relogio_codigo ??= self::novoCodigoDoRelogio();
        });

        static::saving(function (Jogo $jogo): void {
            if ($jogo->eCashGame()) {
                $jogo->estrutura_blinds_id = null;
            }
        });
    }

    /** Letras e números sem os que confundem (0/o, 1/l/i): fácil de digitar no controle da TV. */
    private const ALFABETO_DO_CODIGO = 'abcdefghjkmnpqrstuvwxyz23456789';

    /** Código novo e livre para o link do relógio ("k7m2q9xp"). */
    public static function novoCodigoDoRelogio(): string
    {
        do {
            $codigo = '';
            for ($i = 0; $i < 8; $i++) {
                $codigo .= self::ALFABETO_DO_CODIGO[random_int(0, strlen(self::ALFABETO_DO_CODIGO) - 1)];
            }
        } while (static::query()->where('relogio_codigo', $codigo)->exists());

        return $codigo;
    }

    /**
     * AVISO do gerenciador para as telas do relógio (v3): o número sobe a cada aviso e `ha` diz há
     * quantos segundos foi o último. O navegador toca quando o número sobe e o aviso é recente.
     *
     * @return array{numero: int, ha: ?float}
     */
    public function avisoParaNavegador(): array
    {
        return [
            'numero' => (int) $this->avisos,
            'ha' => $this->aviso_em ? round(max(0, $this->aviso_em->diffInMilliseconds(now())) / 1000, 1) : null,
        ];
    }

    /** Link do relógio só para ver, sem login (v3): o que vai para a TV, o tablet e o grupo. */
    public function linkDoRelogio(): string
    {
        return route('relogio.publico', $this->relogio_codigo);
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

    /** v2: estrutura de blinds do torneio; sem ela, nível e blinds são digitados na noite. */
    public function estruturaBlinds(): BelongsTo
    {
        return $this->belongsTo(EstruturaBlinds::class, 'estrutura_blinds_id');
    }

    /** Nível da estrutura em que o relógio está (Services\Jogo\Relogio). */
    public function relogioNivel(): BelongsTo
    {
        return $this->belongsTo(EstruturaNivel::class, 'relogio_nivel_id');
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

    /** Histórico: quem mudou o quê e quando (Services\Jogo\Historico), do mais novo para o mais antigo. */
    public function alteracoes(): HasMany
    {
        return $this->hasMany(Alteracao::class)->latest()->latest('id');
    }

    public function pontosRanking(): HasMany
    {
        return $this->hasMany(RankingPonto::class);
    }

    public function evento(): HasOne
    {
        return $this->hasOne(Evento::class);
    }

    /** Quem terminou em 1º (torneio finalizado). */
    public function campeao(): HasOne
    {
        return $this->hasOne(Participante::class)->where('posicao_final', 1);
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

    /** O que o site mostra: jogos reais agendados, em andamento ou finalizados. */
    public function scopeNoSite(Builder $query): Builder
    {
        return $query->reais()->comStatus(StatusJogo::Agendado, StatusJogo::EmAndamento, StatusJogo::Finalizado);
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

    /** v2: torneio com estrutura de blinds tem relógio (Services\Jogo\Relogio); sem ela, nível e blinds são digitados. */
    public function temRelogio(): bool
    {
        return $this->eTorneio() && $this->estrutura_blinds_id !== null;
    }

    public function estaFinalizado(): bool
    {
        return $this->status === StatusJogo::Finalizado;
    }

    public function estaNaFase(FaseTorneio $fase): bool
    {
        return $this->status === StatusJogo::EmAndamento && $this->fase === $fase;
    }

    /** O buy-in que o jogo anuncia: o padrão ativo, ou o primeiro buy-in ativo. */
    public function buyinPadrao(): ?Item
    {
        $buyins = $this->itens->filter(fn (Item $item) => $item->tipo === TipoItem::Buyin && $item->ativo);

        return $buyins->firstWhere('padrao', true) ?? $buyins->first();
    }

    /** Ficha de um buy-in: B no padrão, 2 / 3… nas variações que valem 2× / 3× o padrão. */
    public function fichaDoBuyin(Item $item): string
    {
        $padrao = $this->buyinPadrao();
        $base = $padrao ? Dinheiro::centavos($padrao->valor) : 0;
        $valor = Dinheiro::centavos($item->valor);

        if ($item->is($padrao) || $base === 0 || $valor % $base !== 0 || $valor / $base < 2 || $valor / $base > 9) {
            return 'B';
        }

        return (string) intdiv($valor, $base);
    }

    /** Admin do site ainda pode mexer em entradas, opcionais e taxas. */
    public function estruturaEditavel(): bool
    {
        return ! $this->estaFinalizado() && $this->entradas_fechadas_em === null;
    }

    /** Ainda dá para lançar buy-in, rebuy, add-on ou opcional: em andamento e antes de Fechar entradas. */
    public function entradasAbertas(): bool
    {
        return $this->status === StatusJogo::EmAndamento && $this->fase !== FaseTorneio::SemEntradas;
    }
}
