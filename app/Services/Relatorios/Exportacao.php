<?php

namespace App\Services\Relatorios;

use Carbon\CarbonInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Relatório em arquivo. CSV no jeito do Excel em português: separador ";", vírgula decimal ("1890,00")
 * e BOM UTF-8 para os acentos. Excel (.xlsx) pelo OpenSpout (vem com o Filament), com dinheiro como
 * número no formato R$ e data como data.
 */
class Exportacao
{
    public function csv(Relatorio $relatorio): string
    {
        $saida = fopen('php://temp', 'r+');
        fwrite($saida, "\xEF\xBB\xBF");

        fputcsv($saida, array_map(fn (Coluna $c): string => $c->rotulo, $relatorio->colunas), ';', '"', '');
        foreach ($relatorio->linhas as $linha) {
            fputcsv($saida, array_map(fn (Coluna $c): string => self::textoCsv($c, $linha[$c->chave] ?? null), $relatorio->colunas), ';', '"', '');
        }

        rewind($saida);
        $csv = stream_get_contents($saida);
        fclose($saida);

        return $csv;
    }

    /** Grava o .xlsx em $caminho. */
    public function xlsx(Relatorio $relatorio, string $caminho): void
    {
        $writer = new Writer;
        $writer->openToFile($caminho);
        $writer->getCurrentSheet()->setName(mb_substr($relatorio->titulo, 0, 31));

        $negrito = (new Style)->setFontBold();
        $dinheiro = (new Style)->setFormat('"R$" #,##0.00;-"R$" #,##0.00');
        $data = (new Style)->setFormat('dd/mm/yyyy');

        $writer->addRow(Row::fromValues([$relatorio->titulo.' · '.$relatorio->periodo], $negrito));
        $writer->addRow(Row::fromValues(array_map(fn (Coluna $c): string => $c->rotulo, $relatorio->colunas), $negrito));

        foreach ($relatorio->linhas as $linha) {
            $writer->addRow(new Row(array_map(function (Coluna $coluna) use ($linha, $dinheiro, $data): Cell {
                $valor = $linha[$coluna->chave] ?? null;

                return match (true) {
                    $valor === null => Cell::fromValue(null),
                    in_array($coluna->tipo, [Coluna::DINHEIRO, Coluna::RESULTADO], true) => Cell::fromValue(((int) $valor) / 100, $dinheiro),
                    $coluna->tipo === Coluna::INTEIRO => Cell::fromValue((int) $valor),
                    $valor instanceof CarbonInterface => Cell::fromValue($valor->toDateTimeImmutable(), $data),
                    default => Cell::fromValue((string) $valor),
                };
            }, $relatorio->colunas)));
        }

        $writer->close();
    }

    private static function textoCsv(Coluna $coluna, mixed $valor): string
    {
        return match (true) {
            $valor === null => '',
            in_array($coluna->tipo, [Coluna::DINHEIRO, Coluna::RESULTADO], true) => number_format(((int) $valor) / 100, 2, ',', ''),
            $valor instanceof CarbonInterface => $valor->format('d/m/Y'),
            default => (string) $valor,
        };
    }
}
