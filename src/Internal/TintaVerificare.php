<?php

declare(strict_types=1);

namespace VerificRca\Internal;

use VerificRca\Enum\TipVerificare;

/** @internal Cererea validata: ce trimitem la API si dupa ce facem polling. */
final class TintaVerificare
{
    public function __construct(
        public readonly TipVerificare $tip,
        public readonly string $query,
        public readonly ?string $serieSasiu,
        public readonly string $queryPolling,
        public readonly ?string $callbackUrl,
        public readonly ?string $serieTalon = null,
    ) {}
}
