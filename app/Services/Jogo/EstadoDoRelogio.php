<?php

namespace App\Services\Jogo;

use App\Models\EstruturaNivel;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Relógio do torneio num instante (Services\Jogo\Relogio::estado). Tempos em segundos.
 * `numero` é o do nível de blinds (nulo no intervalo); `ultimoNumero` é o último nível de blinds
 * até aqui, o que vai para `jogos.nivel_atual` e para a eliminação. `fracao` é o pedaço do segundo
 * que já passou (correndo), para a contagem no navegador virar no instante certo.
 */
final readonly class EstadoDoRelogio
{
    /** @param  Collection<int, EstruturaNivel>  $niveis  todos, em ordem (índice 0) */
    public function __construct(
        public Collection $niveis,
        public int $indice,
        public ?int $numero,
        public ?int $ultimoNumero,
        public int $restante,
        public bool $rodando,
        public bool $iniciado,
        public CarbonInterface $agora,
        public float $fracao = 0.0,
    ) {}

    /** Segundos que faltam, com a fração (o `restante` é arredondado para cima, como no relógio). */
    public function restanteExato(): float
    {
        return round($this->restante - $this->fracao, 3);
    }

    public function nivel(): EstruturaNivel
    {
        return $this->niveis[$this->indice];
    }

    public function proximo(): ?EstruturaNivel
    {
        return $this->niveis[$this->indice + 1] ?? null;
    }

    public function anterior(): ?EstruturaNivel
    {
        return $this->indice > 0 ? $this->niveis[$this->indice - 1] : null;
    }

    public function duracao(): int
    {
        return $this->nivel()->duracao_minutos * 60;
    }

    public function noIntervalo(): bool
    {
        return $this->nivel()->e_intervalo;
    }

    /** Último nível chegou a 0:00: o relógio para. */
    public function acabou(): bool
    {
        return $this->proximo() === null && $this->restante === 0;
    }

    /** Segundos até o próximo intervalo começar (nulo: no intervalo ou sem intervalo pela frente). */
    public function intervaloEm(): ?int
    {
        if ($this->noIntervalo()) {
            return null;
        }

        $segundos = $this->restante;

        foreach ($this->niveis->slice($this->indice + 1) as $nivel) {
            if ($nivel->e_intervalo) {
                return $segundos;
            }

            $segundos += $nivel->duracao_minutos * 60;
        }

        return null;
    }

    /** Quando o nível atual termina, se o relógio está correndo (para a contagem no navegador). */
    public function terminaEm(): ?CarbonInterface
    {
        return $this->rodando ? $this->agora->copy()->addSeconds($this->restante) : null;
    }

    /** "Nível 6", "Intervalo", "Jantar" */
    public function titulo(): string
    {
        return $this->noIntervalo() ? $this->nivel()->blinds() : 'Nível '.$this->numero;
    }

    /** "14:32", "09:05", "1:05:00" */
    public function tempo(): string
    {
        return self::formatar($this->restante);
    }

    public static function formatar(int $segundos): string
    {
        $segundos = max(0, $segundos);
        $horas = intdiv($segundos, 3600);
        $minutos = str_pad((string) intdiv($segundos % 3600, 60), 2, '0', STR_PAD_LEFT);
        $resto = str_pad((string) ($segundos % 60), 2, '0', STR_PAD_LEFT);

        return ($horas > 0 ? $horas.':' : '').$minutos.':'.$resto;
    }
}
