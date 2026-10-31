<?php

declare(strict_types=1);

namespace Acteo\Enum;

/**
 * Rolul firmei intr-un dosar de insolventa (`firma.dosar`):
 * - `Debitor`: firma e in procedura;
 * - `Creditor`: are de recuperat bani in procedura altcuiva;
 * - `Alta`: alta calitate.
 */
enum InsolventaFirma: string
{
    case Debitor = 'debitor';
    case Creditor = 'creditor';
    case Alta = 'alta';
}
