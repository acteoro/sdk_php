<?php

declare(strict_types=1);

namespace Acteo\Enum;

/**
 * Starea pozei unei masini:
 * - `Gata`: poza masinii cerute (se taxeaza);
 * - `InLucru`: o randam acum; revino dupa `retryAfterMs`;
 * - `Necunoscut`: marca sau modelul nu au fost identificate;
 * - `Indisponibil`: randarea a esuat; se reincearca la prima cerere dupa 24h.
 *
 * In afara de `Gata`, primesti poza generica (masina sub husa), netaxata.
 */
enum StatusImagine: string
{
    case Gata = 'gata';
    case InLucru = 'in-lucru';
    case Necunoscut = 'necunoscut';
    case Indisponibil = 'indisponibil';
}
