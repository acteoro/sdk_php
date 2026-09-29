<?php

declare(strict_types=1);

namespace VerificRca\Internal;

/** @internal */
final class CeasSistem implements Ceas
{
    public function acumMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    public function dormiMs(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
