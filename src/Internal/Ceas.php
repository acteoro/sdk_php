<?php

declare(strict_types=1);

namespace VerificRca\Internal;

/** @internal Sursa de timp a SDK-ului; testele o inlocuiesc ca sa nu doarma. */
interface Ceas
{
    /** Momentul curent, in milisecunde Unix. */
    public function acumMs(): int;

    public function dormiMs(int $ms): void;
}
