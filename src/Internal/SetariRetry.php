<?php

declare(strict_types=1);

namespace VerificRca\Internal;

use VerificRca\Exception\EroareConfigurare;

/**
 * @internal Politica de reincercare pentru erorile trecatoare. Pauzele cresc
 * exponential (x2), cu jitter, si nu scad sub `Retry-After` cand serverul il trimite.
 */
final class SetariRetry
{
    public const IMPLICIT = ['incercari' => 3, 'intarziereInitialaMs' => 500, 'intarziereMaximaMs' => 10000];

    public function __construct(
        public readonly int $incercari,
        public readonly int $intarziereInitialaMs,
        public readonly int $intarziereMaximaMs,
    ) {}

    /**
     * @param array<mixed> $optiuni
     *
     * @throws EroareConfigurare
     */
    public static function dinOptiuni(array $optiuni): self
    {
        foreach ($optiuni as $cheie => $valoare) {
            if (!is_string($cheie) || !array_key_exists($cheie, self::IMPLICIT)) {
                throw new EroareConfigurare(
                    'Optiune `retry` necunoscuta: ' . $cheie . '. Se accepta: incercari, intarziereInitialaMs, intarziereMaximaMs.',
                );
            }
            if (!is_int($valoare) || $valoare < 0) {
                throw new EroareConfigurare("`retry.{$cheie}` trebuie sa fie un numar intreg mai mare sau egal cu 0.");
            }
        }
        $incercari = $optiuni['incercari'] ?? self::IMPLICIT['incercari'];
        $initiala = $optiuni['intarziereInitialaMs'] ?? self::IMPLICIT['intarziereInitialaMs'];
        $maxima = $optiuni['intarziereMaximaMs'] ?? self::IMPLICIT['intarziereMaximaMs'];

        return new self($incercari, $initiala, $maxima);
    }
}
