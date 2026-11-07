<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Seguranca;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cadastro rápido na noite: cria o jogador só com nome (e apelido, se vier) e já confirma.
 * O resto do cadastro (foto, acesso) fica no painel. No site pede `usuarios.cadastro_rapido`; no
 * painel (pré-torneio), `usuarios.criar`, e aceita o telefone.
 */
class CadastrarJogadorRapido
{
    use ChecaRegras;

    public function __construct(private ConfirmarJogador $confirmar) {}

    public function __invoke(Jogo $jogo, string $nome, ?string $apelido, Usuario|Seguranca $quem, ?string $telefone = null): Participante
    {
        $this->permitirNosDois($quem, 'usuarios.cadastro_rapido', 'usuarios.criar', 'Você não tem permissão para cadastrar jogadores.');

        $nome = Str::squish($nome);
        $apelido = filled($apelido) ? Str::squish($apelido) : null;

        if ($nome === '') {
            throw new RegraDoJogo('Informe o nome do jogador.');
        }

        $telefone = filled($telefone) ? Str::squish($telefone) : null;

        return DB::transaction(function () use ($jogo, $nome, $apelido, $quem, $telefone) {
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

            $jogador = Usuario::query()->create(['nome' => $nome, 'apelido' => $apelido, 'telefone' => $telefone, 'ativo' => true]);

            return ($this->confirmar)($jogo, $jogador, $quem);
        });
    }
}
