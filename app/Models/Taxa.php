<?php

namespace App\Models;

use App\Enums\CalculoTaxa;
use App\Support\Dinheiro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Taxa tirada da premiação antes de dividir (dealers, casa…). */
class Taxa extends Model
{
    protected $table = 'jogo_taxas';

    protected $fillable = [
        'jogo_id',
        'nome',
        'calculo',
        'valor',
        'quantidade',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'calculo' => CalculoTaxa::class,
            'valor' => 'decimal:2',
            'quantidade' => 'integer',
            'ordem' => 'integer',
        ];
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class);
    }

    /** Como a taxa aparece no site: "10%", "R$ 190", "2 × R$ 215", "R$ 10 por jogador". */
    public function resumo(): string
    {
        $valor = Dinheiro::brl($this->valor);

        return match ($this->calculo) {
            CalculoTaxa::Percentual => str_replace('.', ',', rtrim(rtrim((string) $this->valor, '0'), '.')).'%',
            CalculoTaxa::Fixo => $valor,
            CalculoTaxa::PorUnidade => max(1, (int) $this->quantidade).' × '.$valor,
            CalculoTaxa::PorJogador => $valor.' por jogador',
            CalculoTaxa::PorEntrada => $valor.' por entrada',
        };
    }
}
