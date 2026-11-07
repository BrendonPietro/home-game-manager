<?php

namespace App\Services\Jogo;

use App\Enums\StatusJogo;
use App\Models\Jogo;
use Illuminate\Support\Facades\DB;

/**
 * Copia a estrutura de um jogo ou modelo para um novo: entradas e opcionais, taxas, premiação (só os
 * percentuais), mesas (mesa, dealer, lugares), rankings, floor, temporada e regras. Não copia lista,
 * lugares, caixa nem andamento: o novo nasce em rascunho. Serve para criar a partir do modelo
 * (CriarDeModelo), duplicar e salvar como modelo.
 */
class CopiaDoJogo
{
    /** O que é da noite e não do cadastro. */
    private const NAO_COPIA = [
        'nome', 'data', 'horario', 'e_modelo', 'nome_modelo', 'status', 'fase', 'nivel_atual', 'blinds_atual',
        'relogio_nivel_id', 'relogio_segundos', 'relogio_desde', 'relogio_iniciado_em', 'relogio_codigo', 'avisos', 'aviso_em',
        'iniciado_em', 'intervalo_aberto_em', 'entradas_fechadas_em', 'finalizado_em',
    ];

    public function __construct(private EventoDoJogo $evento) {}

    /** @param  array{nome: string, data?: ?string, horario?: ?string, e_modelo?: bool, nome_modelo?: ?string}  $atributos */
    public function copiar(Jogo $origem, array $atributos): Jogo
    {
        return DB::transaction(function () use ($origem, $atributos) {
            // Relido do banco: o registro de uma tabela do painel traz contagens (taxas_count…) que o
            // replicate copiaria como colunas.
            $origem = Jogo::query()->with(['itens', 'taxas', 'premiacoes', 'mesas', 'rankings'])->findOrFail($origem->id);

            $novo = $origem->replicate(self::NAO_COPIA);
            $novo->fill($atributos + ['data' => null, 'horario' => $origem->horario, 'e_modelo' => false, 'nome_modelo' => null]);
            $novo->forceFill(['status' => StatusJogo::Rascunho])->save();

            foreach ([$origem->itens, $origem->taxas, $origem->mesas] as $linhas) {
                foreach ($linhas as $linha) {
                    $copia = $linha->replicate();
                    $copia->jogo_id = $novo->id;
                    $copia->save();
                }
            }

            foreach ($origem->premiacoes as $premiacao) {
                $novo->premiacoes()->create(['posicao' => $premiacao->posicao, 'percentual' => $premiacao->percentual]);
            }

            $novo->rankings()->sync($origem->rankings->modelKeys());
            $this->evento->sincronizar($novo);

            return $novo;
        });
    }
}
