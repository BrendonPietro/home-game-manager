<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/** Datas como o grupo fala: "Domingo, 04/10/2026", "Dom 04/10", "Hoje", "Em 4 dias". */
class Datas
{
    /** "Domingo, 04/10/2026" */
    public static function porExtenso(CarbonInterface $data): string
    {
        return Str::ucfirst($data->translatedFormat('l')).', '.$data->format('d/m/Y');
    }

    /** "Dom 04/10/2026" */
    public static function curta(CarbonInterface $data): string
    {
        return Str::ucfirst($data->translatedFormat('D')).' '.$data->format('d/m/Y');
    }

    /** "OUT" */
    public static function mes(CarbonInterface $data): string
    {
        return Str::upper($data->translatedFormat('M'));
    }

    /** "Hoje", "Amanhã", "Em 4 dias", "Ontem", "Há 3 dias" (sempre em dias de calendário). */
    public static function quando(CarbonInterface $data): string
    {
        $dias = (int) today()->diffInDays($data->copy()->startOfDay(), false);

        return match (true) {
            $dias === 0 => 'Hoje',
            $dias === 1 => 'Amanhã',
            $dias === -1 => 'Ontem',
            $dias > 1 => "Em {$dias} dias",
            default => 'Há '.abs($dias).' dias',
        };
    }

    /** "20:00" a partir de "20:00:00" (ou null). */
    public static function hora(?string $hora): ?string
    {
        return $hora ? substr($hora, 0, 5) : null;
    }
}
