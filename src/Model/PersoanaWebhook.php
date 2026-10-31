<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Internal\Decodor;

/** Persoana monitorizata din `asigurare.schimbare`. */
final class PersoanaWebhook
{
    /**
     * @param string $id ID-ul persoanei in cont
     * @param string $nume numele persoanei
     */
    public function __construct(
        public readonly string $id,
        public readonly string $nume,
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
            nume: Decodor::sir($d, 'nume', $prefix),
        );
    }
}
