<?php

declare(strict_types=1);

namespace Acteo\Auto;

use Acteo\Exception\EroareValidare;
use Acteo\Internal\Chei;
use Acteo\Validare;

/** Identificatorul unei verificari RCA: numar de inmatriculare, VIN sau amandoua. */
final class IdentificatorRca
{
    /**
     * @param string|null $numar numarul de inmatriculare
     * @param string|null $vin seria de sasiu (VIN); cu ea, cautarea in AIDA e mai exacta
     */
    public function __construct(
        public readonly ?string $numar = null,
        public readonly ?string $vin = null,
    ) {}

    /**
     * Un text de 17 caractere fara I, O si Q e VIN; altfel e numar de inmatriculare.
     * Un array are cheile `numar` si/sau `vin`.
     *
     * @param string|array{numar?: string|null, vin?: string|null}|self $identificator
     *
     * @throws EroareValidare array cu chei necunoscute
     */
    public static function din(string|array|self $identificator): self
    {
        if ($identificator instanceof self) {
            return $identificator;
        }
        if (is_string($identificator)) {
            $normalizat = Validare::normalizeaza($identificator);

            return Validare::esteVinValid($normalizat) ? new self(vin: $normalizat) : new self(numar: $normalizat);
        }
        Chei::verifica($identificator, ['numar', 'vin']);

        return new self($identificator['numar'] ?? null, $identificator['vin'] ?? null);
    }
}
