<?php

namespace App\Actions\Jogo;

use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cadastro rápido na noite: cria o jogador só com nome (e apelido, se vier) e já confirma.
 * O resto do cadastro (foto, telefone, acesso) fica no painel.
 */
class CadastrarJogadorRapido
{
    public function __construct(private ConfirmarJogador $confirmar) {}

    public function __invoke(Jogo $jogo, string $nome, ?string $apelido, Usuario $quem): Participante
    {
        if (! $quem->can('usuarios.cadastro_rapido')) {
            throw new AuthorizationException('Você não tem permissão para cadastrar jogadores.');
        }

        $nome = Str::squish($nome);
        $apelido = filled($apelido) ? Str::squish($apelido) : null;

        if ($nome === '') {
            throw new RegraDoJogo('Informe o nome do jogador.');
        }

        return DB::transaction(function () use ($jogo, $nome, $apelido, $quem) {
            // Nome ou apelido novo batendo com nome ou apelido de alguém (ex.: "Marcão" já é o Marcos).
            $chaves = array_filter([Str::lower($nome), $apelido ? Str::lower($apelido) : null]);
            $repetido = Usuario::query()
                ->where(function ($q) use ($chaves) {
                    foreach ($chaves as $chave) {
                        $q->orWhereRaw('LOWER(nome) = ?', [$chave])->orWhereRaw('LOWER(apelido) = ?', [$chave]);
                    }
                })
                ->first();

            if ($repetido) {
                throw new RegraDoJogo("Já existe {$repetido->nome_exibicao} no cadastro: confirme pela lista.");
            }

            $jogador = Usuario::query()->create(['nome' => $nome, 'apelido' => $apelido, 'ativo' => true]);

            return ($this->confirmar)($jogo, $jogador, $quem);
        });
    }
}
