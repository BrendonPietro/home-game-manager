{{-- Telas 07, 07m e 11: a noite ao vivo e as fases do torneio (docs/prototipo, telas 07, 07m e 11). Atualiza a cada 5 s. --}}
@php
	use App\Enums\FaseTorneio;
	use App\Support\Dinheiro;

	$torneio = $jogo->eTorneio();
	$blinds = $jogo->blinds_atual ?: '—';
	$nivel = $jogo->nivel_atual ? 'Nível '.$jogo->nivel_atual : 'Blinds';
	$prizePool = Dinheiro::brlCentavos($resumo->prizePool);
	$numeros = $torneio
		? ['Buy-ins' => $contagem['buyins'], 'Rebuys' => $contagem['rebuys'], 'Add-ons' => $contagem['addons']]
		: ['Buy-ins' => $contagem['buyins'], 'Recompras' => $contagem['rebuys'], 'Cash-outs' => $contagem['cashOuts']];
	$podeFases = auth('web')->user()->can('jogo.fases');
	$botaoFase = $torneio && $podeFases ? match ($jogo->fase) {
		FaseTorneio::Rebuys => 'ENCERRAR REBUYS',
		FaseTorneio::Intervalo => 'FECHAR ENTRADAS',
		default => null,
	} : null;
	$quantos = fn (int $n, string $coisa): string => $n.' '.$coisa.($n === 1 ? '' : 's');
@endphp

<div class="rh-aovivo" wire:poll.5s>
	<div class="rh-strip rh-aovivo__faixa">
		@if ($podeFases)
			<button type="button" class="rh-aovivo__nivel" wire:click="abrirNivel" title="Mudar o nível e os blinds">
				<span class="rh-label">{{ $nivel }} @svg('rh-edit')</span><span class="v">{{ $blinds }}</span>
			</button>
		@else
			<div><span class="rh-label">{{ $nivel }}</span><span class="v">{{ $blinds }}</span></div>
		@endif
		@if ($torneio)
			<div><span class="rh-label">Fase</span><span class="v">{{ $jogo->fase?->label() ?? '—' }}</span></div>
		@endif
		<div><span class="rh-label">Jogando</span><span class="v">{{ $jogando }}</span></div>
		<div><span class="rh-label">Entradas</span><span class="v">{{ $resumo->entradas }}</span></div>
		<div><span class="rh-label">Arrecadação</span><span class="v">{{ Dinheiro::brlCentavos($resumo->arrecadacao) }}</span></div>
		<div class="is-prize"><span class="rh-label">Prize pool</span><span class="v">{{ $prizePool }}</span></div>
	</div>

	<div class="rh-aovivo__grade">
		<div class="rh-aovivo__palco">
			<div class="rh-aovivo__barra">
				@if ($mesas->count() > 1)
					<div class="rh-seg" role="group" aria-label="Mesa em exibição">
						@foreach ($mesas as $jogoMesa)
							<button type="button" wire:key="ver-mesa-{{ $jogoMesa->id }}" wire:click="verMesa({{ $jogoMesa->id }})" aria-pressed="{{ $jogoMesa->is($mesaAtual) ? 'true' : 'false' }}">{{ $jogoMesa->mesa->nome }} · {{ $naMesa[$jogoMesa->id] ?? 0 }}</button>
						@endforeach
					</div>
				@elseif ($mesaAtual)
					<h2 class="rh-heading">{{ $mesaAtual->mesa->nome }}</h2>
				@endif

				<span class="rh-spacer"></span>

				<div class="rh-aovivo__legenda" aria-hidden="true">
					<span><span class="rh-chip">B</span>Buy-in</span>
					<span><span class="rh-chip rh-chip--r">R</span>{{ $torneio ? 'Rebuy' : 'Recompra' }}</span>
					@if ($torneio)
						<span><span class="rh-chip rh-chip--a">A</span>Add-on</span>
					@endif
					<span><span class="rh-dealer-btn">D</span>Dealer</span>
				</div>

				@if ($mesaAtual)
					<button type="button" class="rh-btn" x-on:click="$dispatch('girar-mesa', {{ $mesaAtual->id }})">@svg('rh-rotate') GIRAR MESA</button>
				@endif

				{{-- Tablet e celular: a lateral some, o botão da fase vem para cá. --}}
				@if ($botaoFase)
					<button type="button" class="rh-btn rh-btn--danger rh-aovivo__fase-curta" wire:click="abrirFase">@svg('rh-lock') {{ $botaoFase }}</button>
				@endif
			</div>

			@if ($movendoNome)
				<div class="rh-aovivo__mudando" role="status">
					@svg('rh-move')
					<span><b>{{ $movendoNome }}</b>: clique num lugar vazio para mover ou num jogador para trocar{{ $mesas->count() > 1 ? ' (troque de mesa acima)' : '' }}.</span>
					<span class="rh-spacer"></span>
					<button type="button" class="rh-btn rh-btn--sm" wire:click="tirarDoLugar">@svg('rh-out') Tirar do lugar</button>
					<button type="button" class="rh-btn rh-btn--ghost rh-btn--sm" wire:click="cancelarMudanca">Cancelar</button>
				</div>
			@endif

			{{-- Some sozinho: a tela ao vivo fica aberta a noite toda. --}}
			@if ($aviso)
				<div class="rh-warn rh-warn--ok" role="status" wire:key="aviso-{{ md5($aviso) }}" x-data="{ ver: true }" x-init="setTimeout(() => ver = false, 6000)" x-show="ver" x-transition.opacity>@svg('rh-check')<div>{{ $aviso }}</div></div>
			@endif

			@if ($erro && ! $modal)
				<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
			@endif

			<div class="rh-aovivo__mesa">
				@if ($mesaAtual)
					<x-site.poker.mesa
						wire:key="mesa-{{ $mesaAtual->id }}-{{ $mesaAtual->lugares }}"
						:jogo-mesa="$mesaAtual"
						:ocupantes="$ocupantes"
						:rodapes="$rodapes"
						:selecionado="$movendo ?? $selecionado"
						acao="clicarAssento"
						:subtitulo="$jogo->blinds_atual ? 'Blinds '.$jogo->blinds_atual : null"
					/>
				@else
					<p class="rh-muted">Nenhuma mesa neste jogo: adicione uma no painel.</p>
				@endif
			</div>

			@if ($semLugar->isNotEmpty())
				<div class="rh-aovivo__espera">
					<span class="rh-label">Sem lugar</span>
					@foreach ($semLugar as $participante)
						<button
							type="button"
							wire:key="espera-{{ $participante->id }}"
							@class(['rh-pcard', 'is-on' => $selecionado === $participante->id])
							wire:click="selecionar({{ $participante->id }})"
						>
							<x-site.props.avatar :nome="$participante->usuario->nome_exibicao" :foto="$participante->usuario->foto" tamanho="sm" />
							<span class="rh-pcard__n">{{ $participante->usuario->nome_exibicao }}</span>
						</button>
					@endforeach
				</div>
			@endif
		</div>

		<aside class="rh-aovivo__lado">
			<div class="rh-aovivo__sec">
				<div class="rh-label">Prize pool</div>
				<div class="rh-aovivo__prize">{{ $prizePool }}</div>
				<div class="rh-small rh-muted">
					Arrecadado {{ Dinheiro::brlCentavos($resumo->arrecadacao) }}{{ $resumo->totalTaxas ? ' · taxas '.Dinheiro::brlCentavos($resumo->totalTaxas) : '' }}{{ $resumo->foraDaPremiacao ? ' · fora '.Dinheiro::brlCentavos($resumo->foraDaPremiacao) : '' }}
				</div>
			</div>

			<div class="rh-aovivo__sec rh-aovivo__numeros">
				@foreach ($numeros as $rotulo => $valor)
					<div class="rh-stat rh-stat--flat"><span class="rh-label">{{ $rotulo }}</span><span class="rh-stat__v">{{ $valor }}</span></div>
				@endforeach
			</div>

			{{-- Entradas fechadas: o prêmio de cada posição e quem já está nela. --}}
			@if ($premiacao->isNotEmpty())
				<div class="rh-aovivo__sec">
					<div class="rh-label" style="margin-bottom:6px">Premiação</div>
					@foreach ($premiacao as $premio)
						<div class="rh-aovivo__premio" wire:key="premio-{{ $premio['posicao'] }}">
							<span class="rh-aovivo__pos">{{ $premio['posicao'] }}º</span>
							<span @class(['rh-aovivo__quem', 'rh-faint' => ! $premio['quem']])>{{ $premio['quem'] ?? 'em jogo' }}</span>
							<span class="rh-num rh-aovivo__valor">@brl($premio['valor'])</span>
						</div>
					@endforeach
				</div>
			@endif

			<div class="rh-aovivo__sec rh-aovivo__titulo">
				<span class="rh-label">Últimos registros</span>
				<span class="rh-spacer"></span>
				@if ($urlResumo)
					<a class="rh-link rh-small" href="{{ $urlResumo }}">Resumo</a>
				@endif
			</div>

			<div class="rh-list rh-aovivo__feed">
				@forelse ($ultimos as $entrada)
					@php $ficha = $this->fichaDaEntrada($entrada); @endphp
					<div class="rh-li" wire:key="registro-{{ $entrada->id }}">
						@if ($ficha)
							<span @class(['rh-chip', 'rh-chip--'.$ficha[1] => $ficha[1]])>{{ $ficha[0] }}</span>
						@else
							<span class="rh-aovivo__sem-ficha" aria-hidden="true">@svg('rh-plus')</span>
						@endif
						<span class="rh-aovivo__quem"><b>{{ $entrada->participante->usuario->nome_exibicao }}</b> <span class="rh-muted">· {{ $entrada->item->nome }}</span></span>
						<span class="rh-num rh-aovivo__valor">@brl($entrada->valor)</span>
						<span class="rh-small rh-faint">{{ $entrada->created_at->format('H:i') }}</span>
					</div>
				@empty
					<div class="rh-li rh-muted">Nenhuma entrada ainda.</div>
				@endforelse
			</div>

			@if ($botaoFase)
				<div class="rh-aovivo__sec rh-aovivo__acao">
					<button type="button" class="rh-btn rh-btn--danger rh-btn--block" wire:click="abrirFase">@svg('rh-lock') {{ $botaoFase }}</button>
				</div>
			@endif
		</aside>
	</div>

	{{-- Telas 08–09: painel do jogador clicado (fica de pé entre um poll e outro). --}}
	@if ($selecionado)
		<livewire:jogo.acoes-jogador :jogo="$jogo" :participante-id="$selecionado" :key="'acoes-'.$selecionado" />
	@endif

	{{-- Celular: a faixa some e blinds, entradas e prize pool ficam presos embaixo. --}}
	<div class="rh-aovivo__rodape">
		@if ($podeFases)
			<button type="button" class="rh-aovivo__nivel" wire:click="abrirNivel" title="Mudar o nível e os blinds"><span class="rh-label">Blinds @svg('rh-edit')</span><span class="v">{{ $blinds }}</span></button>
		@else
			<div><span class="rh-label">Blinds</span><span class="v">{{ $blinds }}</span></div>
		@endif
		<div><span class="rh-label">Entradas</span><span class="v">{{ $resumo->entradas }}</span></div>
		<div class="is-prize"><span class="rh-label">Prize pool</span><span class="v">{{ $prizePool }}</span></div>
	</div>

	@if ($modal)
		<div class="rh-scrim" wire:keydown.escape.window="fecharModal">
			@if ($modal === 'nivel')
				<form class="rh-modal" style="max-width:380px" wire:submit="salvarNivel" role="dialog" aria-modal="true" aria-labelledby="nivel-titulo">
					<div class="rh-modal__hd">
						<h2 class="rh-heading" id="nivel-titulo">Nível e blinds</h2>
						<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-x" wire:click="fecharModal" aria-label="Fechar">@svg('rh-x')</button>
					</div>
					<div class="rh-modal__bd rh-col" style="gap:12px">
						@if ($erro)
							<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
						@endif
						<div class="rh-field">
							<label class="rh-field__label" for="nivel-campo">Nível</label>
							<div class="rh-row" style="flex-wrap:nowrap">
								<input id="nivel-campo" class="rh-input" wire:model="nivel" inputmode="numeric" autocomplete="off" style="flex:1">
								<button type="button" class="rh-btn" wire:click="$set('nivel', '{{ ((int) $nivel) + 1 }}')">+1</button>
							</div>
						</div>
						<label class="rh-field">
							<span class="rh-field__label">Blinds</span>
							<input class="rh-input" wire:model="blinds" maxlength="40" autocomplete="off" placeholder="100 / 200" x-init="$nextTick(() => $el.focus())">
							<span class="rh-field__help">Como o grupo escreve: small / big (e ante, se tiver).</span>
						</label>
					</div>
					<div class="rh-modal__ft">
						<button type="button" class="rh-btn rh-btn--lg" wire:click="fecharModal">VOLTAR</button>
						<button type="submit" class="rh-btn rh-btn--primary rh-btn--lg" wire:loading.attr="disabled">SALVAR</button>
					</div>
				</form>
			@elseif ($modal === 'encerrar')
				<form class="rh-modal" style="max-width:460px" wire:submit="encerrarRebuys" role="dialog" aria-modal="true" aria-labelledby="encerrar-titulo">
					<div class="rh-modal__hd">
						<h2 class="rh-heading" id="encerrar-titulo" style="font-size:26px">Encerrar rebuys</h2>
						<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-x" wire:click="fecharModal" aria-label="Fechar">@svg('rh-x')</button>
					</div>
					<div class="rh-modal__bd rh-col" style="gap:16px">
						@if ($erro)
							<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
						@endif
						<div class="rh-warn">
							@svg('rh-alert')
							<div>
								<b>Ao encerrar os rebuys:</b>
								<ul>
									<li>novos rebuys não poderão ser registrados;</li>
									@if ($addon)
										<li>o add-on abre ({{ Dinheiro::brl($addon->valor) }}{{ $addon->limite_por_jogador ? ', '.$addon->limite_por_jogador.' por jogador' : '' }});</li>
									@endif
									<li>buy-in atrasado ainda entra até fechar as entradas;</li>
									@if ($foraDeVez->isNotEmpty())
										<li>{{ $foraDeVez->join(', ', ' e ') }} {{ $foraDeVez->count() > 1 ? 'ficam eliminados' : 'fica eliminado' }} de vez.</li>
									@else
										<li>ninguém está fora agora.</li>
									@endif
								</ul>
							</div>
						</div>
						<div class="rh-small rh-muted">{{ $quantos($contagem['rebuys'], 'rebuy') }} até agora · prize pool {{ $prizePool }}</div>
					</div>
					<div class="rh-modal__ft">
						<button type="button" class="rh-btn rh-btn--lg" wire:click="fecharModal">VOLTAR</button>
						<button type="submit" class="rh-btn rh-btn--danger rh-btn--lg" wire:loading.attr="disabled">@svg('rh-lock') ENCERRAR REBUYS</button>
					</div>
				</form>
			@elseif ($modal === 'fechar')
				@php $taxas = $jogo->taxas; @endphp
				<form class="rh-modal" style="max-width:460px" wire:submit="fecharEntradas" role="dialog" aria-modal="true" aria-labelledby="fechar-titulo">
					<div class="rh-modal__hd">
						<h2 class="rh-heading" id="fechar-titulo" style="font-size:26px">Fechar entradas</h2>
						<button type="button" class="rh-btn rh-btn--ghost rh-btn--icon rh-x" wire:click="fecharModal" aria-label="Fechar">@svg('rh-x')</button>
					</div>
					<div class="rh-modal__bd rh-col" style="gap:16px">
						@if ($erro)
							<div class="rh-warn" role="alert">@svg('rh-alert')<div>{{ $erro }}</div></div>
						@endif
						<div class="rh-warn">
							@svg('rh-alert')
							<div>
								<b>Após fechar as entradas:</b>
								<ul>
									<li>novos buy-ins não poderão ser registrados;</li>
									<li>novos rebuys não poderão ser registrados;</li>
									<li>novos add-ons não poderão ser registrados;</li>
									<li>o prêmio de cada posição fica gravado.</li>
								</ul>
							</div>
						</div>
						@if ($semBuyin->isNotEmpty())
							<div class="rh-warn" role="alert">
								@svg('rh-alert')
								<div><b>Sem buy-in:</b> {{ $semBuyin->join(', ', ' e ') }}. Lance antes de fechar ou {{ $semBuyin->count() > 1 ? 'eles ficam' : 'fica' }} fora da premiação.</div>
							</div>
						@endif

						<div class="rh-aovivo__conta">
							<div><span class="rh-muted">Total arrecadado</span><span class="v">{{ Dinheiro::brlCentavos($resumo->arrecadacao) }}</span></div>
							@if ($resumo->foraDaPremiacao)
								<div><span class="rh-muted">Fora da premiação</span><span class="v">−{{ Dinheiro::brlCentavos($resumo->foraDaPremiacao) }}</span></div>
							@endif
							@foreach ($resumo->taxas as $indice => $taxa)
								<div><span class="rh-muted">{{ $taxa['nome'] }}{{ isset($taxas[$indice]) ? ' ('.$taxas[$indice]->resumo().')' : '' }}</span><span class="v">−{{ Dinheiro::brlCentavos($taxa['valor']) }}</span></div>
							@endforeach
							<div class="is-prize"><span class="rh-label">Prize pool</span><span class="v">{{ $prizePool }}</span></div>
						</div>

						@if ($resumo->premios)
							<div class="rh-small">{{ collect($resumo->premios)->map(fn ($p) => $p['posicao'].'º '.Dinheiro::brlCentavos($p['valor']))->join(' · ') }}</div>
						@endif
						<div class="rh-small rh-muted">{{ $quantos($resumo->entradas, 'entrada') }} · {{ $quantos($contagem['buyins'], 'buy-in') }}, {{ $quantos($contagem['rebuys'], 'rebuy') }}, {{ $quantos($contagem['addons'], 'add-on') }}</div>
					</div>
					<div class="rh-modal__ft">
						<button type="button" class="rh-btn rh-btn--lg" wire:click="fecharModal">VOLTAR</button>
						<button type="submit" class="rh-btn rh-btn--primary rh-btn--lg" wire:loading.attr="disabled">@svg('rh-lock') FECHAR ENTRADAS</button>
					</div>
				</form>
			@endif
		</div>
	@endif
</div>
