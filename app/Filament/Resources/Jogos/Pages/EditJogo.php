<?php

namespace App\Filament\Resources\Jogos\Pages;

use App\Actions\Jogo\AgendarJogo;
use App\Actions\Jogo\CancelarJogo;
use App\Actions\Jogo\RefazerPeloCadastro;
use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Filament\Resources\Jogos\JogoResource;
use App\Filament\Resources\Jogos\Pages\Concerns\ConfereAPremiacao;
use App\Services\Jogo\Calculadora;
use App\Services\Jogo\EventoDoJogo;
use App\Services\Jogo\ResumoFinanceiro;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditJogo extends EditRecord
{
    use ConfereAPremiacao;

    protected static string $resource = JogoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('agendar')
                ->label('Agendar')
                ->icon('rh-calendario')
                ->color('primary')
                ->visible(fn (): bool => $this->record->status === StatusJogo::Rascunho)
                ->requiresConfirmation()
                ->modalHeading('Agendar jogo')
                ->modalDescription('O jogo passa a aparecer no site e no calendário. Salve as alterações antes de agendar.')
                ->modalSubmitActionLabel('Agendar')
                ->action(fn () => $this->mudarStatus(app(AgendarJogo::class), 'Jogo agendado.')),
            Action::make('cancelar')
                ->label('Cancelar jogo')
                ->icon('rh-fechar')
                ->color('danger')
                ->outlined()
                ->visible(fn (): bool => $this->record->status->podeCancelar())
                ->requiresConfirmation()
                ->modalHeading('Cancelar jogo')
                ->modalDescription('O jogo sai do site e do calendário e fica só para consulta. Não dá para desfazer.')
                ->modalSubmitActionLabel('Cancelar jogo')
                ->modalCancelActionLabel('Voltar')
                ->action(fn () => $this->mudarStatus(app(CancelarJogo::class), 'Jogo cancelado.')),
            JogoResource::acaoDuplicar(),
            JogoResource::acaoSalvarComoModelo(),
            ViewAction::make(),
            JogoResource::acaoExcluir(),
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

    /** Salvar e refazer o jogo em andamento ficam juntos: se o refazer esbarrar numa regra, nada fica gravado. */
    protected ?bool $hasDatabaseTransactions = true;

    /** Os números de antes, para o histórico de um jogo em andamento (só vive durante o salvar). */
    private ?ResumoFinanceiro $antes = null;

    protected function beforeSave(): void
    {
        // Roda antes de gravar as relações (entradas do cadastro, taxas, premiação): ainda é o "antes".
        if ($this->record->status === StatusJogo::EmAndamento) {
            $this->antes = app(Calculadora::class)->doJogo($this->record->load('taxas', 'premiacoes'));
        }
    }

    protected function afterSave(): void
    {
        app(EventoDoJogo::class)->sincronizar($this->record);

        // Sem `jogos.andamento` as abas de dinheiro ficam travadas: não há o que refazer.
        if ($this->record->status !== StatusJogo::EmAndamento || ! Filament::auth()->user()->can(RefazerPeloCadastro::PERMISSAO)) {
            return;
        }

        try {
            $alteracao = app(RefazerPeloCadastro::class)($this->record, Filament::auth()->user(), $this->antes);
        } catch (RegraDoJogo $erro) {
            Notification::make()->danger()->title('Não salvou')->body($erro->getMessage())->persistent()->send();

            $this->halt(shouldRollbackDatabaseTransaction: true);
        }

        if ($alteracao) {
            Notification::make()->warning()->title('Jogo refeito pelo cadastro')->body($alteracao->descricao)->send();
        }
    }
}
