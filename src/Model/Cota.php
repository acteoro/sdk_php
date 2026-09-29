<?php

declare(strict_types=1);

namespace VerificRca\Model;

/** Cota pe o perioada (zi sau luna). */
final class Cota
{
    /**
     * @param int $limita verificari permise de plan in perioada
     * @param int $ramase verificari ramase, dupa aceasta cerere
     */
    public function __construct(
        public readonly int $limita,
        public readonly int $ramase,
    ) {}
}
