<?php

declare(strict_types=1);

namespace Acteo\Enum;

/**
 * Starea unei verificari de polita PAD:
 * - `Processing`: PADROM nu a raspuns inca; mai intreaba peste cateva secunde;
 * - `Done`: PADROM a raspuns, verdictul e in `valabila`;
 * - `Error`: PADROM a refuzat datele (`INVALID_IDENTIFIER`) sau nu a raspuns. Apare doar in webhook;
 *   `rezultat()` si `asteapta()` arunca `EroareVerificare`.
 */
enum StatusPolitaPad: string
{
    case Processing = 'processing';
    case Done = 'done';
    case Error = 'error';
}
