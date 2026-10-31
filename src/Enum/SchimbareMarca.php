<?php

declare(strict_types=1);

namespace Acteo\Enum;

/** Ce s-a schimbat la o marca urmarita (`marca.schimbare`). `Inregistrata` = cererea in curs a devenit marca inregistrata. */
enum SchimbareMarca: string
{
    case Inregistrata = 'inregistrata';
    case Reinnoita = 'reinnoita';
    case Expirata = 'expirata';
    case Anulata = 'anulata';
    case Respinsa = 'respinsa';
    case Retrasa = 'retrasa';
    case Opozitie = 'opozitie';
}
