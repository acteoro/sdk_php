<?php

declare(strict_types=1);

namespace Acteo\Exception;

/**
 * Verificarea s-a terminat cu statusul error. `cod` spune de ce (vezi
 * `CodEroare::ESEC_VERIFICARE`):
 * - `INVALID_IDENTIFIER`: sursa a refuzat datele trimise, iar `codSursa` spune ce
 *   camp e gresit (`serie_talon_invalida`, `vin_invalid`, `numar_invalid`). O
 *   reincercare cu aceleasi date nu ajuta; corecteaza campul intai;
 * - `UPSTREAM_UNAVAILABLE`, `UPSTREAM_TIMEOUT`, `UPSTREAM_BLOCKED`, `UPSTREAM_CHANGED`:
 *   sursa oficiala nu a dat un raspuns; reia mai tarziu cu o cerere noua.
 *
 * Un server mai vechi, fara cod, da `cod` `VERIFICARE_ESUATA`.
 */
final class EroareVerificare extends ActeoException
{
    /**
     * @param string|null $codSursa campul refuzat de sursa, doar la `INVALID_IDENTIFIER`
     */
    public function __construct(
        string $message,
        ?int $status = null,
        ?string $cod = null,
        ?string $hint = null,
        mixed $raspuns = null,
        ?\Throwable $previous = null,
        public readonly ?string $codSursa = null,
    ) {
        parent::__construct($message, $status, $cod, $hint, $raspuns, $previous);
    }
}
