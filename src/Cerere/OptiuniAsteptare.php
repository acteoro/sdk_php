<?php

declare(strict_types=1);

namespace VerificRca\Cerere;

/** Optiunile pentru `asteapta()` si `verificaSiAsteapta()`. */
final class OptiuniAsteptare
{
    /**
     * @param int $intervalMs pauza intre doua interogari; minim 2000 ms (plafonul API-ului)
     * @param int $timeoutMs cat asteptam in total
     */
    public function __construct(
        public readonly int $intervalMs = 3000,
        public readonly int $timeoutMs = 300000,
    ) {}
}
