{{--
	Opcionais que o jogador pagou antes (taxa da pizza…), um selo cada: "Taxa Comida · +40.000". Entram junto do
	buy-in quando ele senta (Actions\Jogo\MarcarOpcionalPagoAntes). Quem chama carrega `opcionaisPagos`.
	<x-site.props.pagos-antes :participante="$participante" />
--}}
@props(['participante'])

@foreach ($participante->opcionaisPagos->filter(fn ($opcional) => $opcional->pago_antes && $opcional->ativo) as $opcional)
	<span class="rh-badge rh-badge--ok">{{ $opcional->nome }}{{ $opcional->fichas ? ' · +'.\App\Support\Fichas::numero((int) $opcional->fichas) : '' }}</span>
@endforeach
