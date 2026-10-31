<?php

declare(strict_types=1);

namespace Acteo\Enum;

/** De unde vine anul de fabricatie la decodarea VIN: `Pozitia10` confirmata, startul `Generatie` sau `Anunturi`. */
enum SursaAn: string
{
    case Pozitia10 = 'pozitia-10';
    case Generatie = 'generatie';
    case Anunturi = 'anunturi';
}
