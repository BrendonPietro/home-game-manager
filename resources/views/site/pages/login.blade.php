{{-- Tela 01: login do site de operação (docs/prototipo, tela 01). Entra pelo e-mail: `usuarios` não tem coluna de login. --}}
<x-site.app titulo="Entrar">
	<div class="rh-login">
		<div class="rh-login__arc" aria-hidden="true"></div>

		<form class="rh-login__card" method="POST" action="{{ route('login') }}" novalidate>
			@csrf

			<x-site.props.brand />

			<div>
				<h1 class="rh-heading">Entrar na operação</h1>
				<p class="rh-small rh-muted" style="margin:4px 0 0">Acesso para quem toca a noite: mesas, entradas e fechamento.</p>
			</div>

			<x-site.props.field label="E-mail" name="email" type="email" autocomplete="username" inputmode="email" required autofocus />
			<x-site.props.field label="Senha" name="password" type="password" autocomplete="current-password" required />

			<label class="rh-check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Lembrar acesso</label>

			<button type="submit" class="rh-btn rh-btn--primary rh-btn--lg rh-btn--block">Entrar</button>

			<p class="rh-small rh-faint" style="margin:0;text-align:center">Esqueceu a senha? Fale com o organizador.</p>
		</form>

		<div class="rh-login__foot">Rodrigues Home Game · gerenciamento de torneios presenciais</div>
	</div>
</x-site.app>
