<?php

declare(strict_types=1);

namespace Acteo\Internal;

use Acteo\Exception\EroareValidare;

/** @internal Opreste local o cheie scrisa gresit intr-un identificator dat ca array. */
final class Chei
{
    private function __construct() {}

    /**
     * @param array<mixed> $date
     * @param list<string> $permise
     *
     * @throws EroareValidare
     */
    public static function verifica(array $date, array $permise): void
    {
        foreach (array_keys($date) as $cheie) {
            if (!in_array($cheie, $permise, true)) {
                throw new EroareValidare(
                    "Cheie necunoscuta in identificator: {$cheie}. Se accepta: " . implode(', ', $permise) . '.',
                    cod: 'VALIDARE_LOCALA',
                    acceptate: $permise,
                    camp: 'identificator',
                );
            }
        }
    }
}
