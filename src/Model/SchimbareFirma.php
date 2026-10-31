<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\CampFirma;
use Acteo\Internal\Decodor;

/** Un camp schimbat de ANAF la o firma monitorizata. */
final class SchimbareFirma
{
    /**
     * @param CampFirma $camp campul schimbat
     * @param string|null $valoareVeche valoarea dinainte (`"da"` / `"nu"` sau textul ANAF); null cand nu e cunoscuta
     * @param string $valoareNoua valoarea de acum
     * @param string|null $dataEfect data (`yyyy-mm-dd`) de la care se aplica, cand ANAF o da
     */
    public function __construct(
        public readonly CampFirma $camp,
        public readonly ?string $valoareVeche,
        public readonly string $valoareNoua,
        public readonly ?string $dataEfect,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d, string $prefix): self
    {
        return new self(
            camp: Decodor::enum($d, 'camp', $prefix, CampFirma::class),
            valoareVeche: Decodor::sirSauNull($d, 'valoareVeche', $prefix),
            valoareNoua: Decodor::sir($d, 'valoareNoua', $prefix),
            dataEfect: Decodor::sirSauNull($d, 'dataEfect', $prefix),
        );
    }
}
