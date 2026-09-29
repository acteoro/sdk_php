<?php

declare(strict_types=1);

namespace VerificRca\Cerere;

use VerificRca\Enum\TipVerificare;

/**
 * Ce trebuie ca sa interoghezi rezultatul unei verificari fara obiectul
 * `VerificareInCurs` primit de la `porneste()`.
 */
final class ReferintaVerificare
{
    /**
     * @param TipVerificare $tip tipul verificarii
     * @param string $query identificatorul dupa care se face pollingul (la RCA cu VIN, VIN-ul)
     * @param string|null $serieSasiu doar la rovinieta
     * @param int|null $since momentul (ms) cererii POST; fara el, orice rezultat din ultima
     *   perioada e acceptat, chiar daca e mai vechi decat cererea ta
     */
    public function __construct(
        public readonly TipVerificare $tip,
        public readonly string $query,
        public readonly ?string $serieSasiu = null,
        public readonly ?int $since = null,
    ) {}
}
