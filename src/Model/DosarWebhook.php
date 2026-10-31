<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Internal\Decodor;

/** Dosarul monitorizat din `dosar.eveniment` si `dosar.termen`. */
final class DosarWebhook
{
    /**
     * @param string $id ID-ul dosarului in cont
     * @param string $numar numarul dosarului la instanta, ex: `1234/3/2024`
     * @param string|null $eticheta eticheta pusa de tine in cont
     */
    public function __construct(
        public readonly string $id,
        public readonly string $numar,
        public readonly ?string $eticheta,
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
            numar: Decodor::sir($d, 'numar', $prefix),
            eticheta: Decodor::sirSauNull($d, 'eticheta', $prefix),
        );
    }
}
