<?php

namespace App\Models;

use App\Enums\TipoAlteracao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma linha do histórico do jogo: quem (site ou painel), o quê, quando e por quê. Só se cria pelo
 * `Services\Jogo\Historico`, dentro das Actions; nunca se edita nem se apaga.
 */
class Alteracao extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'alteracoes';

    protected $fillable = [
        'jogo_id',
        'usuario_id',
        'seguranca_id',
        'tipo',
        'descricao',
        'motivo',
        'dados',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoAlteracao::class,
            'dados' => 'array',
            'created_at' => 'datetime',
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

    public function conta(): BelongsTo
    {
        return $this->belongsTo(Seguranca::class, 'seguranca_id');
    }

    /** "Marcão" (site) ou "dev (painel)". */
    public function quem(): string
    {
        return match (true) {
            $this->usuario !== null => $this->usuario->nome_exibicao,
            $this->conta !== null => $this->conta->login.' (painel)',
            default => '—',
        };
    }
}
