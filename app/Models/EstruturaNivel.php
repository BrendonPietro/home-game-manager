<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um nível da estrutura: small, big, ante (0 = sem) e duração. Intervalo é um nível sem blinds,
 * com nome ("Intervalo", "Jantar"). O número do nível (intervalo não conta) sai do EstruturaBlinds::linhas().
 */
class EstruturaNivel extends Model
{
    protected $table = 'estrutura_niveis';

    protected $fillable = [
        'estrutura_id',
        'ordem',
        'small_blind',
        'big_blind',
        'ante',
        'duracao_minutos',
        'e_intervalo',
        'nome_intervalo',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'small_blind' => 'integer',
            'big_blind' => 'integer',
            'ante' => 'integer',
            'duracao_minutos' => 'integer',
            'e_intervalo' => 'boolean',
        ];
    }

    /** Intervalo não guarda blinds; nível de blinds não guarda nome de intervalo. */
    protected static function booted(): void
    {
        static::saving(function (EstruturaNivel $nivel): void {
            if ($nivel->e_intervalo) {
                $nivel->forceFill(['small_blind' => null, 'big_blind' => null, 'ante' => 0]);
                $nivel->nome_intervalo = $nivel->nome_intervalo ?: 'Intervalo';
            } else {
                $nivel->forceFill(['nome_intervalo' => null, 'ante' => (int) $nivel->ante]);
            }
        });
    }

    public function estrutura(): BelongsTo
    {
        return $this->belongsTo(EstruturaBlinds::class, 'estrutura_id');
    }

    /** "100 / 200", "1.000 / 2.000"; no intervalo, o nome dele. */
    public function blinds(): string
    {
        if ($this->e_intervalo) {
            return $this->nome_intervalo ?: 'Intervalo';
        }

        return self::fichas($this->small_blind).' / '.self::fichas($this->big_blind);
    }

    /** "100 / 200 · ante 200", ou só os blinds quando não tem ante. */
    public function blindsComAnte(): string
    {
        return $this->blinds().(! $this->e_intervalo && $this->ante > 0 ? ' · ante '.self::fichas($this->ante) : '');
    }

    /** 10000 → "10.000" */
    public static function fichas(?int $quantidade): string
    {
        return number_format((int) $quantidade, 0, ',', '.');
    }
}
