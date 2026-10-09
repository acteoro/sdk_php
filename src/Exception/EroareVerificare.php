<?php

declare(strict_types=1);

namespace VerificRca\Exception;

/**
 * Verificarea s-a terminat cu statusul error; `cod` e mereu `VERIFICARE_ESUATA`.
 *
 * Fara `codSursa`, sursa oficiala nu a raspuns la timp: reia cu o cerere noua.
 * Cu `codSursa` (`serie_talon_invalida`, `vin_invalid`, `numar_invalid`), sursa a
 * refuzat datele trimise: corecteaza-le, altfel o cerere noua da acelasi rezultat.
 */
final class EroareVerificare extends VerificRcaException
{
    /**
     * @param string|null $codSursa motivul trimis de API cand sursa a refuzat datele
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
