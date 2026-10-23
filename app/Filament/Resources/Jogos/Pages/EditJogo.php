<?php

namespace App\Filament\Resources\Jogos\Pages;

use App\Actions\Jogo\AgendarJogo;
use App\Actions\Jogo\CancelarJogo;
use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Filament\Resources\Jogos\JogoResource;
use App\Services\Jogo\EventoDoJogo;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditJogo extends EditRecord
{
    protected static string $resource = JogoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('agendar')
                ->label('Agendar')
                ->icon('rh-cal')
                ->color('primary')
                ->visible(fn (): bool => $this->record->status === StatusJogo::Rascunho)
                ->requiresConfirmation()
                ->modalHeading('Agendar jogo')
                ->modalDescription('O jogo passa a aparecer no site e no calendário. Salve as alterações antes de agendar.')
                ->modalSubmitActionLabel('Agendar')
                ->action(fn () => $this->mudarStatus(app(AgendarJogo::class), 'Jogo agendado.')),
            Action::make('cancelar')
                ->label('Cancelar jogo')
                ->icon('rh-x')
                ->color('danger')
                ->outlined()
                ->visible(fn (): bool => $this->record->status->podeCancelar())
                ->requiresConfirmation()
                ->modalHeading('Cancelar jogo')
                ->modalDescription('O jogo sai do site e do calendário e fica só para consulta. Não dá para desfazer.')
                ->modalSubmitActionLabel('Cancelar jogo')
                ->modalCancelActionLabel('Voltar')
                ->action(fn () => $this->mudarStatus(app(CancelarJogo::class), 'Jogo cancelado.')),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    private function mudarStatus(callable $acao, string $mensagem): void
    {
        try {
            $acao($this->record);
        } catch (RegraDoJogo $erro) {
            Notification::make()->danger()->title($erro->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($mensagem)->send();

        // Cancelado vira só leitura; agendado continua na edição com o status novo.
        $this->redirect(static::getResource()::getUrl(
            $this->record->refresh()->status === StatusJogo::Cancelado ? 'view' : 'edit',
            ['record' => $this->record],
        ));
    }

    protected function afterSave(): void
    {
        app(EventoDoJogo::class)->sincronizar($this->record);
    }
}
