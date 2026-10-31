<?php

declare(strict_types=1);

namespace Acteo\Enum;

/** Unghiul pozei unei masini. Implicit `TreiSferturi`. */
enum UnghiImagine: string
{
    case TreiSferturi = 'trei-sferturi';
    case Fata = 'fata';
    case Profil = 'profil';
    case Spate = 'spate';
    case TreiSferturiDreapta = 'trei-sferturi-dreapta';
    case ProfilDreapta = 'profil-dreapta';
}
