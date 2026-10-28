<?php

declare(strict_types=1);

namespace Acteo\Internal;

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
}
