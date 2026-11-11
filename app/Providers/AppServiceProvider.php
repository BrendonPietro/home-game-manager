<?php

namespace App\Providers;

use App\Http\Middleware\AcessoDoSite;
use App\Models\Seguranca;
use App\Support\Dinheiro;
use App\Support\IconesDoPainel;
use BladeUI\Icons\Factory;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ícones do site e do painel, todos em resources/svg/icones (troque o arquivo para trocar o ícone):
        // @svg('rh-inicio') no site; 'rh-…' nos ícones do painel e nos do Filament (App\Support\IconesDoPainel).
        $this->callAfterResolving(Factory::class, function (Factory $icones) {
            $icones->add('rh', [
                'path' => resource_path('svg/icones'),
                'prefix' => 'rh',
                'class' => 'rh-ic',
                'attributes' => ['aria-hidden' => 'true'],
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // @brl($valor) → R$ 1.890 · @brl($valor, true) → +R$ 795 / −R$ 200
        Blade::directive('brl', fn (string $expressao) => '<?php echo e(\\'.Dinheiro::class."::brl({$expressao})); ?>");

        // Cada clique do Livewire passa de novo pela regra de acesso do site (conta ativa, Operador só na noite).
        Livewire::addPersistentMiddleware([AcessoDoSite::class]);

        // Os ícones do próprio Filament (editar, excluir, setas, avisos…) vêm de resources/svg/icones, como os do site.
        FilamentIcon::register(IconesDoPainel::apelidos());

        // Contas do painel: registra o último acesso (inclusive login por "lembrar acesso"),
        // sem mexer no updated_at da conta.
        Event::listen(function (Login $evento) {
            if ($evento->guard === 'painel' && $evento->user instanceof Seguranca) {
                Seguranca::withoutTimestamps(
                    fn () => $evento->user->forceFill(['ultimo_acesso' => now()])->saveQuietly(),
                );
            }
        });
    }
}
