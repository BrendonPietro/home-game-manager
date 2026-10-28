<?php

namespace App\Services\Relatorios;

/**
 * Um relatório pronto: as colunas, as linhas (valores crus, pela chave da coluna) e os números de
 * resumo já formatados para os cartões da tela.
 */
final readonly class Relatorio
{
    /**
     * @param  list<Coluna>  $colunas
     * @param  list<array<string, mixed>>  $linhas
     * @param  list<array{rotulo: string, valor: string, detalhe?: string}>  $resumo
     */
    public function __construct(
        public string $tipo,
        public string $titulo,
        public string $periodo,
        public array $colunas,
        public array $linhas,
        public array $resumo = [],
        public ?string $aviso = null,
    ) {}

    /** Nome do arquivo exportado, sem extensão: "relatorio-premiacoes-2026-10-27". */
    public function arquivo(): string
    {
        return 'relatorio-'.str_replace('_', '-', $this->tipo).'-'.today()->format('Y-m-d');
    }
}
