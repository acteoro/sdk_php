<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Internal\Decodor;

/** Generatia unui model, aleasa dupa an. */
final class GeneratieMasina
{
    /**
     * @param string $eticheta numele generatiei, ex: `Volkswagen Golf VII`
     * @param int $anDe primul an al generatiei
     * @param int|null $anPana ultimul an; null = inca in productie
     * @param string $caroserie caroseria, ex: `5-door hatchback`
     */
    public function __construct(
        public readonly string $eticheta,
        public readonly int $anDe,
        public readonly ?int $anPana,
        public readonly string $caroserie,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d, string $prefix): self
    {
        return new self(
            eticheta: Decodor::sir($d, 'eticheta', $prefix),
            anDe: Decodor::intreg($d, 'anDe', $prefix),
            anPana: Decodor::intregSauNull($d, 'anPana', $prefix),
            caroserie: Decodor::sir($d, 'caroserie', $prefix),
        );
    }
}
