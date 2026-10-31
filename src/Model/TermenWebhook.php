<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Internal\Decodor;

/** Termenul din cont din `termen.expira`. */
final class TermenWebhook
{
    /**
     * @param string $id ID-ul termenului in cont
     * @param string $categorie `auto`, `personale`, `sanatate`, `afaceri` sau `casa`
     * @param string $tip tipul termenului
     * @param string $eticheta numele termenului, ex: Pasaport sau numele dat de tine
     * @param string|null $al al cui e termenul: numarul masinii, firma sau persoana
     * @param string $expiraLa data scadentei, `yyyy-mm-dd`
     */
    public function __construct(
        public readonly string $id,
        public readonly string $categorie,
        public readonly string $tip,
        public readonly string $eticheta,
        public readonly ?string $al,
        public readonly string $expiraLa,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d, string $prefix): self
    {
        return new self(
            id: Decodor::sir($d, 'id', $prefix),
            categorie: Decodor::sir($d, 'categorie', $prefix),
            tip: Decodor::sir($d, 'tip', $prefix),
            eticheta: Decodor::sir($d, 'eticheta', $prefix),
            al: Decodor::sirSauNull($d, 'al', $prefix),
            expiraLa: Decodor::sir($d, 'expiraLa', $prefix),
        );
    }
}
