<?php

namespace App\Filament\Resources\Jogos\Pages\Concerns;

use App\Enums\MetodoPremiacao;
use App\Enums\Modalidade;
use App\Filament\Resources\Jogos\Schemas\JogoForm;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Criar e editar jogo ou modelo: antes de validar, as posições da premiação manual sem percentual somem
 * (adicionou e não preencheu = a posição não existe) e, se os percentuais não somam 100%, um alerta pede
 * para ajustar e o jogo não salva.
 */
trait ConfereAPremiacao
{
    protected function beforeValidate(): void
    {
        $premiacoes = $this->data['premiacoes'] ?? null;

        if (! is_array($premiacoes)) {
            return;
        }

        $preenchidas = array_filter($premiacoes, fn (mixed $item): bool => filled(JogoForm::percentualDoItem($item)));
        $this->data['premiacoes'] = $preenchidas;

        $metodo = $this->data['metodo_premiacao'] ?? null;
        $modalidade = $this->data['modalidade'] ?? null;
        $manual = ($metodo instanceof MetodoPremiacao ? $metodo : MetodoPremiacao::tryFrom((string) $metodo)) === MetodoPremiacao::Manual;
        $torneio = ($modalidade instanceof Modalidade ? $modalidade : Modalidade::tryFrom((string) $modalidade)) !== Modalidade::CashGame;

        if (! $manual || ! $torneio) {
            return;
        }

        $soma = JogoForm::somaDaPremiacao($preenchidas);

        if ($soma === 10000) {
            return;
        }

        Notification::make()
            ->danger()
            ->title($preenchidas === [] ? 'Falta a premiação' : 'A premiação não fecha 100%')
            ->body($preenchidas === []
                ? 'Na premiação manual, preencha o percentual de cada posição premiada, somando 100%.'
                : JogoForm::textoDaSoma($soma).'. Ajuste os percentuais e salve de novo.')
            ->persistent()
            ->actions([Action::make('ok')->label('OK')->close()])
            ->send();

        $this->halt();
    }
}
