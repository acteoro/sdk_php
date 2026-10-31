<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\InsolventaFirma;
use Acteo\Internal\Decodor;

/** Dosarul nou in instanta pe numele unei firme monitorizate. */
final class DosarFirma
{
    /**
     * @param string $numar numarul dosarului
     * @param string $institutie instanta
     * @param string|null $calitateParte calitatea firmei in dosar
     * @param string|null $obiect obiectul dosarului
     * @param string|null $categorie categoria de pe portalul instantelor (ex: Faliment)
     * @param InsolventaFirma|null $insolventa rolul firmei intr-un dosar de insolventa; null pentru alt fel de dosar
     */
    public function __construct(
        public readonly string $numar,
        public readonly string $institutie,
        public readonly ?string $calitateParte,
        public readonly ?string $obiect,
        public readonly ?string $categorie,
        public readonly ?InsolventaFirma $insolventa,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d, string $prefix): self
    {
        return new self(
            numar: Decodor::sir($d, 'numar', $prefix),
            institutie: Decodor::sir($d, 'institutie', $prefix),
            calitateParte: Decodor::sirSauNull($d, 'calitateParte', $prefix),
            obiect: Decodor::sirSauNull($d, 'obiect', $prefix),
            categorie: Decodor::sirSauNull($d, 'categorie', $prefix),
            insolventa: Decodor::enumSauNull($d, 'insolventa', $prefix, InsolventaFirma::class),
        );
    }
}
