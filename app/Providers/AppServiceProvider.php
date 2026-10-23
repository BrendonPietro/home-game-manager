<?php

namespace App\Providers;

use App\Models\Seguranca;
use App\Support\Dinheiro;
use BladeUI\Icons\Factory;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ícones do design system: @svg('rh-home') ou <x-rh-home />
        $this->callAfterResolving(Factory::class, function (Factory $icones) {
            $icones->add('rh', [
                'path' => resource_path('svg/site/icons'),
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
