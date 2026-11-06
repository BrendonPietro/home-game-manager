<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Estrutura de blinds reutilizável ("Turbo 15 min", "Deepstack 20 min"), com os níveis em ordem.
 * O torneio escolhe uma (`jogos.estrutura_blinds_id`); quem não escolhe digita nível e blinds na noite.
 */
class EstruturaBlinds extends Model
{
    protected $table = 'estruturas_blinds';

    protected $fillable = [
        'nome',
        'descricao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function niveis(): HasMany
    {
        return $this->hasMany(EstruturaNivel::class, 'estrutura_id')->orderBy('ordem')->orderBy('id');
    }

    public function jogos(): HasMany
    {
        return $this->hasMany(Jogo::class, 'estrutura_blinds_id');
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /**
     * Cópia com os mesmos níveis, para montar uma variação ("Turbo 12 min"): nome "X (cópia)", ou
     * "X (cópia 2)" e assim por diante quando já existe (o nome é único).
     */
    public function duplicar(): static
    {
        return DB::transaction(function (): static {
            $nome = $this->nome.' (cópia)';

            for ($n = 2; static::query()->where('nome', $nome)->exists(); $n++) {
                $nome = "{$this->nome} (cópia {$n})";
            }

            $copia = static::query()->create(['nome' => $nome, 'descricao' => $this->descricao, 'ativo' => $this->ativo]);

            foreach ($this->niveis as $nivel) {
                $copia->niveis()->create($nivel->only([
                    'ordem', 'small_blind', 'big_blind', 'ante', 'duracao_minutos', 'e_intervalo', 'nome_intervalo',
                ]));
            }

            return $copia;
        });
    }

    /** "16 níveis de 15 min · 1 intervalo · 4h15" */
    public function resumo(): string
    {
        $niveis = $this->niveis->reject->e_intervalo;
        $intervalos = $this->niveis->where('e_intervalo', true)->count();

        if ($niveis->isEmpty()) {
            return 'Sem níveis';
        }

        $duracoes = $niveis->pluck('duracao_minutos')->unique();
        $partes = [
            $niveis->count().' '.($niveis->count() === 1 ? 'nível' : 'níveis')
                .($duracoes->count() === 1 ? ' de '.$duracoes->first().' min' : ''),
        ];

        if ($intervalos > 0) {
            $partes[] = $intervalos.' '.($intervalos === 1 ? 'intervalo' : 'intervalos');
        }

        $partes[] = self::duracao($this->niveis->sum('duracao_minutos'));

        return implode(' · ', $partes);
    }

    /**
     * Linhas para mostrar a estrutura (prévia no painel, aba Blinds do jogo, conferência): número do
     * nível, blinds, ante, duração e quando começa contando do início do relógio. Aceita os models ou
     * o estado do formulário (arrays ainda não salvos).
     *
     * @param  iterable<EstruturaNivel|array<string, mixed>>  $niveis
     * @return list<array{numero: ?int, intervalo: bool, blinds: string, ante: ?string, minutos: int, comeca: string}>
     */
    public static function linhas(iterable $niveis): array
    {
        $linhas = [];
        $numero = 0;
        $minutos = 0;

        foreach ($niveis as $nivel) {
            $nivel = $nivel instanceof EstruturaNivel ? $nivel : new EstruturaNivel(array_intersect_key((array) $nivel, array_flip([
                'small_blind', 'big_blind', 'ante', 'duracao_minutos', 'e_intervalo', 'nome_intervalo',
            ])));
            $intervalo = (bool) $nivel->e_intervalo;
            $duracao = max(0, (int) $nivel->duracao_minutos);

            $linhas[] = [
                'numero' => $intervalo ? null : ++$numero,
                'intervalo' => $intervalo,
                'blinds' => $intervalo || filled($nivel->big_blind) ? $nivel->blinds() : '—',
                'ante' => ! $intervalo && $nivel->ante > 0 ? EstruturaNivel::fichas($nivel->ante) : null,
                'minutos' => $duracao,
                'comeca' => intdiv($minutos, 60).':'.str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT),
            ];

            $minutos += $duracao;
        }

        return $linhas;
    }

    /** 255 → "4h15", 45 → "45 min", 120 → "2h" */
    public static function duracao(int $minutos): string
    {
        if ($minutos < 60) {
            return $minutos.' min';
        }

        $resto = $minutos % 60;

        return intdiv($minutos, 60).'h'.($resto ? str_pad((string) $resto, 2, '0', STR_PAD_LEFT) : '');
    }
}
