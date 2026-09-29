<?php

declare(strict_types=1);

namespace VerificRca\Cerere;

use VerificRca\Enum\TipVerificare;

/** Datele pentru pornirea unei verificari. */
final class CerereVerificare
{
    /**
     * @param TipVerificare|string $tip `rca`, `itp`, `rovinieta` sau `arr` (enum sau text)
     * @param string $query identificatorul principal:
     *   - rca: numar de inmatriculare sau VIN;
     *   - itp: VIN (RAR nu cauta dupa numar);
     *   - rovinieta: numar de inmatriculare (VIN-ul merge in `serieSasiu`);
     *   - arr: numar de inmatriculare (ARR nu cauta dupa VIN).
     * @param string|null $serieSasiu seria de sasiu (VIN); obligatorie la rovinieta, recomandata la RCA
     * @param string|null $callbackUrl URL http(s) unde API-ul trimite rezultatul prin POST cand e gata
     */
    public function __construct(
        public readonly TipVerificare|string $tip,
        public readonly string $query,
        public readonly ?string $serieSasiu = null,
        public readonly ?string $callbackUrl = null,
    ) {}
}
