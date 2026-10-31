<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\OficiuMarca;
use Acteo\Internal\Decodor;

/** Marca urmarita din `marca.schimbare` si `marca.expira`. */
final class MarcaWebhook
{
    /**
     * @param string $id ID-ul marcii in cont
     * @param string $st13 ID-ul marcii in TMview (oficiul + numarul)
     * @param string $nume numele marcii
     * @param OficiuMarca $oficiu OSIM sau EUIPO
     * @param string $numarDepozit numarul de depozit
     */
    public function __construct(
        public readonly string $id,
        public readonly string $st13,
        public readonly string $nume,
        public readonly OficiuMarca $oficiu,
        public readonly string $numarDepozit,
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
            st13: Decodor::sir($d, 'st13', $prefix),
            nume: Decodor::sir($d, 'nume', $prefix),
            oficiu: Decodor::enum($d, 'oficiu', $prefix, OficiuMarca::class),
            numarDepozit: Decodor::sir($d, 'numarDepozit', $prefix),
        );
    }
}
