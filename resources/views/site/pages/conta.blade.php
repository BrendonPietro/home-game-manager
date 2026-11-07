{{-- Minha conta: trocar a própria senha (ContaController). O resto do cadastro fica no painel. --}}
<x-site.app titulo="Minha conta">
	<x-site.window.topbar />

	<main class="rh-page">
		<div class="rh-wrap rh-col" style="max-width:520px;gap:16px">
			<x-site.props.aviso />

			<div>
				<h1 class="rh-title">Minha conta</h1>
				<div class="rh-muted">{{ $usuario->nome }} · {{ $usuario->email }}</div>
			</div>

			<form class="rh-panel" method="POST" action="{{ route('conta.atualizar') }}" novalidate>
				@csrf
				@method('PUT')

				<div class="rh-panel__hd"><h3 class="rh-heading">Trocar a senha</h3></div>
				<div class="rh-panel__bd rh-col" style="gap:12px">
					<x-site.props.field label="Senha atual" name="senha_atual" type="password" autocomplete="current-password" required />
					<x-site.props.field label="Senha nova" name="senha" type="password" autocomplete="new-password" ajuda="Pelo menos 8 caracteres" required />
					<x-site.props.field label="Senha nova de novo" name="senha_confirmation" type="password" autocomplete="new-password" required />
					<button type="submit" class="rh-btn rh-btn--primary rh-btn--lg rh-btn--block">TROCAR SENHA</button>
				</div>
			</form>

			<p class="rh-small rh-muted" style="margin:0">Nome, e-mail e foto se mudam no painel, em Usuários</p>
		</div>
	</main>
</x-site.app>
