<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Internal\Decodor;

/** Raspunsul DGPCI pentru un numar de inmatriculare. */
final class NumarDisponibil
{
    /**
     * @param string $numar numarul normalizat
     * @param bool $disponibil numarul e liber la DGPCI si poate fi rezervat
     * @param string|null $mesaj textul intors de DGPCI, cand exista
     * @param Limite|null $limite cota ramasa dupa aceasta cerere, cand API-ul o trimite
     */
    public function __construct(
        public readonly string $numar,
        public readonly bool $disponibil,
        public readonly ?string $mesaj,
        public readonly ?Limite $limite = null,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d, ?Limite $limite = null): self
    {
        return new self(
            numar: Decodor::sir($d, 'numar', ''),
            disponibil: Decodor::bool($d, 'disponibil', ''),
            mesaj: Decodor::sirSauNull($d, 'mesaj', ''),
            limite: $limite,
        );
    }
}
