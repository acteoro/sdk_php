<?php

declare(strict_types=1);

namespace VerificRca\Tests\Ajutor;

use VerificRca\Internal\Ceas;

/** Ceas care nu doarme: inregistreaza pauzele si avanseaza timpul. */
final class CeasFals implements Ceas
{
    /** @var list<int> */
    public array $somnuri = [];

    public function __construct(public int $acum = 1790000000000) {}

    public function acumMs(): int
    {
        return $this->acum;
    }

    public function dormiMs(int $ms): void
    {
        $this->somnuri[] = $ms;
        $this->acum += $ms;
    }
}
