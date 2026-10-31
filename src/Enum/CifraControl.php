<?php

declare(strict_types=1);

namespace Acteo\Enum;

/** Cifra de control a VIN-ului (pozitia 9). */
enum CifraControl: string
{
    case Ok = 'ok';
    case Gresita = 'gresita';
    case Neaplicabila = 'neaplicabila';
}
