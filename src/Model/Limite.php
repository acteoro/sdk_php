<?php

declare(strict_types=1);

namespace VerificRca\Model;

/** Cat mai ai din cota planului, din headerele `X-RateLimit-*`. */
final class Limite
{
    /**
     * @param Cota $zi cota zilnica; se reseteaza la miezul noptii
     * @param Cota $luna cota lunara
     */
    public function __construct(
        public readonly Cota $zi,
        public readonly Cota $luna,
    ) {}
}
