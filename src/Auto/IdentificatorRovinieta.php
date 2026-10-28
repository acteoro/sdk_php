<?php

declare(strict_types=1);

namespace Acteo\Auto;

use Acteo\Exception\EroareValidare;
use Acteo\Internal\Chei;

/** Identificatorii unei verificari de rovinieta, toti obligatorii. */
final class IdentificatorRovinieta
{
    /**
     * @param string $numar numarul de inmatriculare
     * @param string $vin seria de sasiu (VIN)
     * @param string $serieTalon numarul certificatului de inmatriculare (talon), ex: `I00639603F`
     */
    public function __construct(
        public readonly string $numar,
        public readonly string $vin,
        public readonly string $serieTalon,
    ) {}

    /**
     * @param array{numar?: string, vin?: string, serieTalon?: string}|self $identificator
     *
     * @throws EroareValidare array cu chei necunoscute
     */
    public static function din(array|self $identificator): self
    {
        if ($identificator instanceof self) {
            return $identificator;
        }
        Chei::verifica($identificator, ['numar', 'vin', 'serieTalon']);

        // O cheie lipsa ramane goala si e raportata de validarea cererii.
        return new self($identificator['numar'] ?? '', $identificator['vin'] ?? '', $identificator['serieTalon'] ?? '');
    }
}
