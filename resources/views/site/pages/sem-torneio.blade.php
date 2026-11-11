{{--
	Fora da janela do torneio, para quem não é admin (App\Support\JanelaDoSite): a pessoa continua logada,
	não vê nada do torneio e fica sabendo quando o próximo abre. Dentro da janela, o site.acesso leva para o jogo.
--}}
@php
	use App\Support\Datas;
@endphp

<x-site.app titulo="Nenhum torneio agora">
	<div class="rh-login">
		<div class="rh-login__arc" aria-hidden="true"></div>

		<div class="rh-login__card">
			<x-site.props.brand />

			<div>
				<h1 class="rh-heading">Nenhum torneio agora</h1>
				<p class="rh-small rh-muted" style="margin:4px 0 0">
					{{ $proximo
						? 'O próximo é '.$proximo->nome.', '.mb_strtolower(Datas::porExtenso($proximo->data)).(Datas::hora($proximo->horario) ? ' às '.Datas::hora($proximo->horario) : '').'. O site abre para você '.($abre->isToday() ? 'hoje' : ($abre->isTomorrow() ? 'amanhã' : mb_strtolower(Datas::porExtenso($abre)))).' às '.$abre->format('H:i')
						: 'Quando o próximo torneio for marcado, o site abre 3 horas antes do início' }}
				</p>
			</div>

			<a href="{{ route('home') }}" class="rh-btn rh-btn--primary rh-btn--lg rh-btn--block">ATUALIZAR</a>

			<form method="POST" action="{{ route('logout') }}">
				@csrf
				<button type="submit" class="rh-btn rh-btn--ghost rh-btn--block">@svg('rh-sair') Sair</button>
			</form>
		</div>

		<div class="rh-login__foot">Rodrigues Home Game · gerenciamento de torneios presenciais</div>
	</div>
</x-site.app>
