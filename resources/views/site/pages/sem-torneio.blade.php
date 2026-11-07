{{-- Fim da noite para quem só opera (sem site.fora_da_noite): sem jogo em andamento, o site não tem nada. --}}
<x-site.app titulo="Nenhum torneio em andamento">
	<div class="rh-login">
		<div class="rh-login__arc" aria-hidden="true"></div>

		<div class="rh-login__card">
			<x-site.props.brand />

			<div>
				<h1 class="rh-heading">Nenhum torneio em andamento</h1>
				<p class="rh-small rh-muted" style="margin:4px 0 0">A sessão foi encerrada. O acesso volta quando o próximo torneio começar</p>
			</div>

			<a href="{{ route('login') }}" class="rh-btn rh-btn--primary rh-btn--lg rh-btn--block">Entrar de novo</a>
		</div>

		<div class="rh-login__foot">Rodrigues Home Game · gerenciamento de torneios presenciais</div>
	</div>
</x-site.app>
