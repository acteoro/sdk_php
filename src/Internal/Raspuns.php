<?php

declare(strict_types=1);

namespace Acteo\Internal;

use Acteo\Model\Cota;
use Acteo\Model\Limite;

/** @internal Un raspuns 2xx, cu corpul JSON deja decodat. */
final class Raspuns
{
    /** @param array<string, string> $headere chei lowercase */
    public function __construct(
        public readonly int $status,
        public readonly array $headere,
        public readonly mixed $corp,
    ) {}

    public function header(string $nume): ?string
    {
        return $this->headere[strtolower($nume)] ?? null;
    }

    /** Cat mai ai din cota, din headerele `X-RateLimit-*`; null daca lipseste vreunul. */
    public function limite(): ?Limite
    {
        $valori = [];
        foreach (['x-ratelimit-limit-day', 'x-ratelimit-remaining-day', 'x-ratelimit-limit-month', 'x-ratelimit-remaining-month'] as $nume) {
            $valoare = $this->header($nume);
            if ($valoare === null || !is_numeric($valoare)) {
                return null;
            }
            $valori[] = (int) $valoare;
        }

        return new Limite(new Cota($valori[0], $valori[1]), new Cota($valori[2], $valori[3]));
    }
}
