<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Internal\Decodor;

/** Firma monitorizata din `firma.schimbare` si `firma.dosar`. */
final class FirmaWebhook
{
    /**
     * @param string $id ID-ul firmei in cont
     * @param string $denumire denumirea firmei
     */
    public function __construct(
        public readonly string $id,
        public readonly string $denumire,
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
            denumire: Decodor::sir($d, 'denumire', $prefix),
        );
    }
}
