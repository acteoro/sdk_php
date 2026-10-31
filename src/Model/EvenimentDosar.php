<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\TipEvenimentDosar;
use Acteo\Internal\Decodor;

/** Ce s-a intamplat intr-un dosar monitorizat. */
final class EvenimentDosar
{
    /**
     * @param TipEvenimentDosar $tip felul noutatii
     * @param string $descriere textul alertei, ca in email
     * @param string|null $institutie instanta, cand e cunoscuta
     */
    public function __construct(
        public readonly TipEvenimentDosar $tip,
        public readonly string $descriere,
        public readonly ?string $institutie,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d, string $prefix): self
    {
        return new self(
            tip: Decodor::enum($d, 'tip', $prefix, TipEvenimentDosar::class),
            descriere: Decodor::sir($d, 'descriere', $prefix),
            institutie: Decodor::sirSauNull($d, 'institutie', $prefix),
        );
    }
}
