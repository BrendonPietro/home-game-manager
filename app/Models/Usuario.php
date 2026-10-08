<?php

namespace App\Models;

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
    /** @use HasFactory<\Database\Factories\UsuarioFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    protected $guard_name = 'web';

    protected $fillable = [
        'nome',
        'apelido',
        'telefone',
        'foto',
        'acessa_site',
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

    /** Como o nome aparece na mesa: apelido, se tiver. */
    protected function nomeExibicao(): Attribute
    {
        return Attribute::get(fn () => $this->apelido ?: $this->nome);
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
}
