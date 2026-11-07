<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Imagick;
use ImagickDraw;
use ImagickPixel;

/**
 * Gera os ícones do site e do painel a partir do logo (`resources/images/logo.png`, quadrado):
 * `public/favicon.ico` (16, 32 e 48), `public/icone-192.png` (aba em tela de alta densidade e atalho
 * do Android) e `public/apple-touch-icon.png` (180, sem transparência: o iOS arredonda sozinho).
 * O favicon e o de 192 saem com os cantos arredondados, no fundo claro do próprio logo, que aparece
 * igual em aba clara e escura. Só roda onde tem Imagick (o local); os arquivos gerados vão no repositório.
 */
#[Signature('icones:gerar {--origem= : Imagem de origem (padrão: resources/images/logo.png)}')]
#[Description('Gera o favicon e os ícones do site e do painel a partir do logo')]
class GerarIcones extends Command
{
    /** Raio dos cantos, em fração do lado. */
    private const RAIO = 0.18;

    public function handle(): int
    {
        if (! extension_loaded('imagick')) {
            $this->error('Precisa da extensão Imagick do PHP (o Laragon tem; a hospedagem não precisa).');

            return self::FAILURE;
        }

        $origem = $this->option('origem') ?: resource_path('images/logo.png');

        if (! is_file($origem)) {
            $this->error("Não achei a imagem {$origem}.");

            return self::FAILURE;
        }

        $logo = $this->quadrado(new Imagick($origem));

        $ico = new Imagick;
        foreach ([16, 32, 48] as $lado) {
            $ico->addImage($this->icone($logo, $lado, arredondado: true));
        }
        $ico->setFormat('ico');
        $ico->writeImages(public_path('favicon.ico'), true);

        $this->icone($logo, 192, arredondado: true)->writeImage('png32:'.public_path('icone-192.png'));
        $this->icone($logo, 180, arredondado: false)->writeImage('png24:'.public_path('apple-touch-icon.png'));

        $this->info('Pronto: public/favicon.ico, public/icone-192.png e public/apple-touch-icon.png');

        return self::SUCCESS;
    }

    /** Recorta o centro num quadrado (a origem já deve ser quadrada; isto só evita ícone torto). */
    private function quadrado(Imagick $imagem): Imagick
    {
        $lado = min($imagem->getImageWidth(), $imagem->getImageHeight());
        $imagem->cropImage($lado, $lado, intdiv($imagem->getImageWidth() - $lado, 2), intdiv($imagem->getImageHeight() - $lado, 2));
        $imagem->setImagePage(0, 0, 0, 0);
        $imagem->setImageFormat('png');

        return $imagem;
    }

    private function icone(Imagick $logo, int $lado, bool $arredondado): Imagick
    {
        $icone = clone $logo;
        $icone->resizeImage($lado, $lado, Imagick::FILTER_LANCZOS, 1);

        // Em 32 e 48, uma nitidez leve para o R não virar borrão; em 16 ela só granula (fica sem).
        if ($lado > 16 && $lado <= 48) {
            $icone->unsharpMaskImage(0, 0.5, 0.4, 0.05);
        }

        if (! $arredondado) {
            $icone->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);

            return $icone;
        }

        $mascara = new Imagick;
        $mascara->newImage($lado, $lado, new ImagickPixel('transparent'), 'png');
        $desenho = new ImagickDraw;
        $desenho->setFillColor(new ImagickPixel('white'));
        $raio = $lado * self::RAIO;
        $desenho->roundRectangle(0, 0, $lado - 1, $lado - 1, $raio, $raio);
        $mascara->drawImage($desenho);

        $icone->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        $icone->compositeImage($mascara, Imagick::COMPOSITE_DSTIN, 0, 0);

        return $icone;
    }
}
