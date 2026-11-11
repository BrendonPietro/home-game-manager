<?php

namespace App\Models;

use App\Enums\DirecaoPagamento;
use App\Enums\Modalidade;
use App\Enums\StatusJogo;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Todas as pessoas do clube: jogadores, dealers, floor e quem opera o site (guard `web`).
 * Jogador sem login fica com `acessa_site = false` e sem grupo.
 */
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    protected $guard_name = 'web';

    protected $fillable = [
        'nome',
        'apelido',
        'telefone',
        'foto',
        'acessa_site',
        'login',
        'email',
        'password',
        'pode_ser_dealer',
        'pode_ser_floor',
        'ativo',
        'observacoes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'acessa_site' => 'boolean',
            'pode_ser_dealer' => 'boolean',
            'pode_ser_floor' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    /** Usuário do login do site, gravado limpo (minúsculas, sem espaço); vazio = entra só pelo e-mail. */
    protected function login(): Attribute
    {
        return Attribute::make(set: fn (?string $valor): ?string => filled($valor) ? mb_strtolower(trim($valor)) : null);
    }

    /** Como o nome aparece na mesa: apelido, se tiver. */
    protected function nomeExibicao(): Attribute
    {
        return Attribute::get(fn () => $this->apelido ?: $this->nome);
    }

    /** Para escolher quem é (listas de quem senta e do sorteio): "Ricardo Alves (RICO)"; sem apelido, só o nome. */
    protected function nomeComApelido(): Attribute
    {
        return Attribute::get(function (): string {
            [$nome, $apelido] = [trim((string) $this->nome), trim((string) $this->apelido)];

            return $apelido !== '' && mb_strtolower($apelido) !== mb_strtolower($nome) ? "{$nome} ({$apelido})" : $nome;
        });
    }

    /** Tem conta ativa no painel com o mesmo usuário ou e-mail (as contas do clube ganham as duas): o menu do site mostra o link do painel. */
    public function temContaNoPainel(): bool
    {
        $logins = array_values(array_filter([$this->login, $this->email ? mb_strtolower($this->email) : null]));

        return $logins !== [] && Seguranca::query()->whereIn('login', $logins)->where('ativo', true)->exists();
    }

    public function participacoes(): HasMany
    {
        return $this->hasMany(Participante::class);
    }

    public function pontosRanking(): HasMany
    {
        return $this->hasMany(RankingPonto::class);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function scopeDealers(Builder $query): Builder
    {
        return $query->where('pode_ser_dealer', true);
    }

    public function scopeFloors(Builder $query): Builder
    {
        return $query->where('pode_ser_floor', true);
    }

    /**
     * Soma dos saldos da pessoa nos jogos finalizados (a mesma conta do Services\Jogo\Saldos, em SQL
     * para a lista do painel poder ordenar): investido − prêmio (ou cash-out) − pago + recebido.
     * Positivo: deve ao caixa; negativo: o caixa deve a ela. Coluna `saldo_em_aberto` (decimal).
     */
    public function scopeComSaldoEmAberto(Builder $query): Builder
    {
        $dosFinalizados = 'join participantes p on p.id = %s.participante_id join jogos j on j.id = p.jogo_id
            where p.usuario_id = usuarios.id and j.status = ?';

        // selectRaw não põe o "*" sozinho.
        if ($query->getQuery()->columns === null) {
            $query->select('usuarios.*');
        }

        return $query->selectRaw(
            '(select coalesce(sum(e.valor), 0) from entradas e '.sprintf($dosFinalizados, 'e').' and e.cancelada_em is null)
            - (select coalesce(sum(case when j.modalidade = ? then coalesce(p.cash_out, 0) else coalesce(p.premio, 0) end), 0)
                from participantes p join jogos j on j.id = p.jogo_id where p.usuario_id = usuarios.id and j.status = ?)
            - (select coalesce(sum(pg.valor), 0) from pagamentos pg '.sprintf($dosFinalizados, 'pg').' and pg.cancelado_em is null and pg.direcao = ?)
            + (select coalesce(sum(pg.valor), 0) from pagamentos pg '.sprintf($dosFinalizados, 'pg').' and pg.cancelado_em is null and pg.direcao = ?)
            as saldo_em_aberto',
            [
                StatusJogo::Finalizado->value,
                Modalidade::CashGame->value, StatusJogo::Finalizado->value,
                StatusJogo::Finalizado->value, DirecaoPagamento::JogadorPagou->value,
                StatusJogo::Finalizado->value, DirecaoPagamento::JogadorRecebeu->value,
            ],
        );
    }
}
