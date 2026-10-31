<?php

declare(strict_types=1);

namespace Acteo\Enum;

/** Statutul de asigurat la CNAS (`asigurare.schimbare`). */
enum StatutAsigurare: string
{
    case Asigurat = 'asigurat';
    case Neasigurat = 'neasigurat';
    case Necunoscut = 'necunoscut';
}
