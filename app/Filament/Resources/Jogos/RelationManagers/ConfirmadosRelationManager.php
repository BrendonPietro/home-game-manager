<?php

namespace App\Filament\Resources\Jogos\RelationManagers;

use App\Actions\Jogo\CadastrarJogadorRapido;
use App\Actions\Jogo\ConfirmarJogador;
use App\Actions\Jogo\DesconfirmarJogador;
use App\Actions\Jogo\DesfazerPagamento;
use App\Actions\Jogo\RegistrarPagamento;
use App\Enums\DirecaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Pagamento;
use App\Models\Participante;
use App\Models\Seguranca;
use App\Models\Usuario;
use App\Support\Dinheiro;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Pré-torneio no painel (aba Confirmados do jogo): a lista de quem vai jogar e o que cada um já pagou
 * antes (buy-in, churrasco…). No dia, quem chega é sentado no site e o buy-in entra ao sentar; quem
 * pagou e não veio não entra no torneio, e o pagamento fica como crédito dele (o caixa devolve).
 *
 * Tudo passa pelas Actions (ConfirmarJogador, CadastrarJogadorRapido, DesconfirmarJogador e
 * RegistrarPagamento), com as permissões do painel: `jogos.editar`, `usuarios.criar` e `pagamentos.registrar`.
 */
class ConfirmadosRelationManager extends RelationManager
{
    protected static string $relationship = 'participantes';

    protected static ?string $title = 'Confirmados';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Jogo
            && ! $ownerRecord->e_modelo
            && $ownerRecord->status !== StatusJogo::Rascunho
            && (bool) Filament::auth()->user()?->can('jogos.ver');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->participantes()->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (Participante $participante): string => $participante->nome())
            ->modifyQueryUsing(fn (Builder $query) => $query->with('usuario')->withSum([
                'pagamentos as pago' => fn (Builder $q) => $q->validas()->where('direcao', DirecaoPagamento::JogadorPagou),
            ], 'valor')->withSum([
                'pagamentos as devolvido' => fn (Builder $q) => $q->validas()->where('direcao', DirecaoPagamento::JogadorRecebeu),
            ], 'valor'))
            ->defaultSort('id')
            ->paginated(false)
            ->emptyStateHeading('Ninguém confirmado ainda')
            ->emptyStateDescription('Confirme quem vai jogar: no dia, é só sentar cada um na mesa pelo site.')
            ->columns([
                TextColumn::make('usuario.nome')
                    ->label('Jogador')
                    ->description(fn (Participante $p): ?string => $p->usuario->apelido)
                    ->searchable(['nome', 'apelido']),
                TextColumn::make('usuario.telefone')
                    ->label('Telefone')
                    ->placeholder('—'),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->state(fn (Participante $p): string => $this->situacao($p))
                    ->badge()
                    ->color(fn (Participante $p): string => match (true) {
                        $this->naoVeio($p) => 'warning',
                        $p->status === StatusParticipante::Confirmado => 'gray',
                        default => 'success',
                    }),
                TextColumn::make('pago')
                    ->label('Pago antes')
                    ->state(fn (Participante $p): int => Dinheiro::centavos($p->pago) - Dinheiro::centavos($p->devolvido))
                    ->formatStateUsing(fn (int $state): string => $state ? Dinheiro::brlCentavos($state) : '—')
                    ->alignEnd()
                    ->visible(fn (): bool => (bool) Filament::auth()->user()?->can('financeiro.ver')),
            ])
            ->headerActions([
                $this->acaoConfirmar(),
                $this->acaoNovo(),
            ])
            ->recordActions([
                $this->acaoPagamento(),
                $this->acaoDesfazerPagamento(),
                $this->acaoTirar(),
            ]);
    }

    /** Pagou antes e não sentou (jogo já começou): não entrou no torneio; o pagamento é crédito dele. */
    private function naoVeio(Participante $participante): bool
    {
        return $participante->status === StatusParticipante::Confirmado
            && $this->jogo()->status !== StatusJogo::Agendado;
    }

    private function situacao(Participante $participante): string
    {
        $credito = Dinheiro::centavos($participante->pago) - Dinheiro::centavos($participante->devolvido);

        return match (true) {
            $this->naoVeio($participante) => $credito > 0 ? 'Não veio · devolver '.Dinheiro::brlCentavos($credito) : 'Não veio',
            default => $participante->status->label(),
        };
    }

    private function jogo(): Jogo
    {
        /** @var Jogo */
        return $this->getOwnerRecord();
    }

    private function conta(): Seguranca
    {
        /** @var Seguranca */
        return Filament::auth()->user();
    }

    private function listaAberta(): bool
    {
        return in_array($this->jogo()->status, [StatusJogo::Agendado, StatusJogo::EmAndamento], true);
    }

    private function acaoConfirmar(): Action
    {
        return Action::make('confirmar')
            ->label('Confirmar jogadores')
            ->icon('rh-plus')
            ->visible(fn (): bool => $this->listaAberta() && $this->conta()->can('jogos.editar'))
            ->modalHeading('Confirmar jogadores')
            ->modalDescription('Quem vai jogar. No dia, quem senta na mesa entra com o buy-in.')
            ->modalSubmitActionLabel('Confirmar')
            ->schema([
                Select::make('usuarios')
                    ->label('Jogadores')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->options(fn (): array => Usuario::query()->ativos()
                        ->whereDoesntHave('participacoes', fn (Builder $q) => $q->where('jogo_id', $this->jogo()->id))
                        ->orderBy('nome')
                        ->get()
                        ->mapWithKeys(fn (Usuario $u): array => [$u->id => $u->apelido ? "{$u->nome} ({$u->apelido})" : $u->nome])
                        ->all()),
            ])
            ->action(function (array $data): void {
                $usuarios = Usuario::query()->whereKey($data['usuarios'])->get();

                $this->executar(function () use ($usuarios) {
                    foreach ($usuarios as $usuario) {
                        app(ConfirmarJogador::class)($this->jogo(), $usuario, $this->conta());
                    }
                }, $usuarios->count() === 1 ? '1 jogador confirmado.' : $usuarios->count().' jogadores confirmados.');
            });
    }

    private function acaoNovo(): Action
    {
        return Action::make('novo')
            ->label('Jogador novo')
            ->icon('rh-users')
            ->color('gray')
            ->visible(fn (): bool => $this->listaAberta() && $this->conta()->can('usuarios.criar'))
            ->modalHeading('Jogador novo')
            ->modalDescription('Cadastra só com o nome (o resto fica em Usuários) e já confirma neste jogo.')
            ->modalSubmitActionLabel('Cadastrar e confirmar')
            ->schema([
                TextInput::make('nome')->required()->maxLength(255),
                TextInput::make('apelido')->maxLength(60),
                TextInput::make('telefone')->tel()->maxLength(30),
            ])
            ->action(fn (array $data) => $this->executar(
                fn () => app(CadastrarJogadorRapido::class)($this->jogo(), $data['nome'], $data['apelido'] ?? null, $this->conta(), $data['telefone'] ?? null),
                "{$data['nome']} cadastrado e confirmado.",
            ));
    }

    /** Pagamento antecipado (ou devolução): crédito do jogador no caixa deste jogo. */
    private function acaoPagamento(): Action
    {
        return Action::make('pagamento')
            ->label('Pagamento')
            ->icon('rh-coins')
            ->color('gray')
            ->visible(fn (): bool => $this->jogo()->status !== StatusJogo::Cancelado && $this->conta()->can('pagamentos.registrar'))
            ->modalHeading(fn (Participante $record): string => 'Pagamento · '.$record->nome())
            ->modalDescription('Entra no caixa deste jogo e abate do que ele vai dever. Se ele não vier, vira crédito para devolver.')
            ->modalSubmitActionLabel('Registrar')
            ->schema([
                ToggleButtons::make('direcao')
                    ->label('O jogador')
                    ->options([
                        DirecaoPagamento::JogadorPagou->value => 'Pagou',
                        DirecaoPagamento::JogadorRecebeu->value => 'Recebeu (devolução)',
                    ])
                    ->default(DirecaoPagamento::JogadorPagou->value)
                    ->inline()
                    ->required(),
                TextInput::make('valor')
                    ->label('Valor')
                    ->prefix('R$')
                    ->required()
                    ->default(fn (): string => Dinheiro::paraTexto($this->previsto()))
                    ->helperText(fn (): string => 'Buy-in e obrigatórios: '.Dinheiro::brlCentavos($this->previsto())),
                ToggleButtons::make('forma')
                    ->label('Forma')
                    ->options(collect(FormaPagamento::disponiveis())->mapWithKeys(fn (FormaPagamento $f): array => [$f->value => $f->label()])->all())
                    ->default(FormaPagamento::Pix->value)
                    ->inline()
                    ->required(),
                TextInput::make('observacao')
                    ->label('Referente a')
                    ->placeholder('Buy-in antecipado, churrasco…')
                    ->default('Pago antes do torneio')
                    ->maxLength(255),
            ])
            ->action(function (Participante $record, array $data): void {
                $centavos = Dinheiro::lerTexto((string) $data['valor']);

                if ($centavos === null) {
                    Notification::make()->danger()->title('Valor inválido: digite, por exemplo, 100 ou 100,50.')->send();

                    return;
                }

                $direcao = DirecaoPagamento::from($data['direcao']);

                $this->executar(
                    fn () => app(RegistrarPagamento::class)($record, $direcao, $centavos, FormaPagamento::from($data['forma']), $this->conta(), (string) Str::uuid(), $data['observacao'] ?? null),
                    $record->nome().($direcao === DirecaoPagamento::JogadorPagou ? ' pagou ' : ' recebeu ').Dinheiro::brlCentavos($centavos).'.',
                );
            });
    }

    /** Pagamento lançado errado: escolhe qual e diz o motivo (fica no histórico do jogo). */
    private function acaoDesfazerPagamento(): Action
    {
        $desfazeis = fn (Participante $record) => $record->pagamentos()->validas()->whereNull('acerto_id')->orderBy('id')->get();

        return Action::make('desfazerPagamento')
            ->label('Desfazer pagamento')
            ->icon('rh-undo')
            ->color('gray')
            ->visible(fn (Participante $record): bool => in_array($this->jogo()->status, [StatusJogo::Agendado, StatusJogo::EmAndamento, StatusJogo::Finalizado], true)
                && $this->conta()->can('pagamentos.registrar')
                && $desfazeis($record)->isNotEmpty())
            ->modalHeading(fn (Participante $record): string => 'Desfazer pagamento · '.$record->nome())
            ->modalDescription('O pagamento fica cancelado (nada é apagado) e a correção fica no histórico do jogo, com o motivo.')
            ->modalSubmitActionLabel('Desfazer')
            ->schema(fn (Participante $record): array => [
                Select::make('pagamento')
                    ->label('Pagamento')
                    ->required()
                    ->options($desfazeis($record)->mapWithKeys(fn (Pagamento $p): array => [
                        $p->id => ($p->direcao === DirecaoPagamento::JogadorPagou ? 'Pagou ' : 'Recebeu ').Dinheiro::brl($p->valor)
                            .' ('.mb_strtolower($p->forma->label()).') · '.$p->created_at->format('d/m H:i')
                            .($p->observacao ? ' · '.$p->observacao : ''),
                    ])->all()),
                TextInput::make('motivo')->label('Motivo')->required()->maxLength(255),
            ])
            ->action(function (Participante $record, array $data): void {
                $pagamento = $record->pagamentos()->findOrFail($data['pagamento']);

                $this->executar(
                    fn () => app(DesfazerPagamento::class)($pagamento, $data['motivo'], $this->conta()),
                    'Pagamento de '.$record->nome().' desfeito.',
                );
            });
    }

    private function acaoTirar(): Action
    {
        return Action::make('tirar')
            ->label('Tirar da lista')
            ->icon('rh-x')
            ->color('danger')
            ->visible(fn (Participante $record): bool => $this->listaAberta()
                && $record->status === StatusParticipante::Confirmado
                && $this->conta()->can('jogos.editar'))
            ->requiresConfirmation()
            ->modalHeading(fn (Participante $record): string => 'Tirar '.$record->nome().' da lista')
            ->modalDescription('Só sai quem não tem pagamento nem lançamento neste jogo.')
            ->modalSubmitActionLabel('Tirar')
            ->action(fn (Participante $record) => $this->executar(
                fn () => app(DesconfirmarJogador::class)($this->jogo(), $record->usuario, $this->conta()),
                $record->nome().' saiu da lista.',
            ));
    }

    /** O que cada um deve ao entrar: buy-in padrão + opcionais obrigatórios "1 por jogador" e de buy-in. */
    private function previsto(): int
    {
        $itens = $this->jogo()->itens()->ativos()->get();
        $buyin = $itens->where('tipo', TipoItem::Buyin)->firstWhere('padrao', true) ?? $itens->firstWhere('tipo', TipoItem::Buyin);

        return Dinheiro::centavos($buyin?->valor)
            + $itens->where('tipo', TipoItem::Opcional)->where('obrigatorio', true)
                ->filter(fn ($opcional) => $opcional->porJogador() || $opcional->compravelCom(TipoItem::Buyin))
                ->sum(fn ($opcional) => Dinheiro::centavos($opcional->valor));
    }

    private function executar(callable $acao, string $sucesso): void
    {
        try {
            $acao();
        } catch (RegraDoJogo|AuthorizationException $erro) {
            Notification::make()->danger()->title($erro->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($sucesso)->send();
    }
}
