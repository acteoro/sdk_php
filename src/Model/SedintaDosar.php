<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Internal\Decodor;

/** Sedinta care se apropie intr-un dosar monitorizat. */
final class SedintaDosar
{
    /**
     * @param \DateTimeImmutable $data data sedintei
     * @param string|null $ora ora sedintei, cand e cunoscuta
     * @param string|null $institutie instanta
     * @param string|null $complet completul de judecata
     */
    public function __construct(
        public readonly \DateTimeImmutable $data,
        public readonly ?string $ora,
        public readonly ?string $institutie,
        public readonly ?string $complet,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d, string $prefix): self
    {
        return new self(
            data: Decodor::data($d, 'data', $prefix),
            ora: Decodor::sirSauNull($d, 'ora', $prefix),
            institutie: Decodor::sirSauNull($d, 'institutie', $prefix),
            complet: Decodor::sirSauNull($d, 'complet', $prefix),
        );
    }
}
