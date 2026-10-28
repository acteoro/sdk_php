<?php

declare(strict_types=1);

namespace Acteo\Internal;

/** @internal Sursa de timp a SDK-ului; testele o inlocuiesc ca sa nu doarma. */
interface Ceas
{
    /** Momentul curent, in milisecunde Unix. */
    public function acumMs(): int;

    public function dormiMs(int $ms): void;
}
