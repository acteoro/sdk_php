<?php

declare(strict_types=1);

namespace Acteo\Exception;

/**
 * 5xx: eroare pe serverul API-ului. Cererile sigure se reincearca automat. La
 * numerele disponibile, `cod` spune de ce: `OCUPAT`, `INDISPONIBIL`,
 * `EROARE_DGPCI` sau `TIMEOUT`; cererea nu s-a taxat.
 */
final class EroareServer extends ActeoException
{
    /**
     * @param int|null $retryAfterMs cat sa astepti inainte de o cerere noua, din headerul Retry-After
     */
    public function __construct(
        string $message,
        ?int $status = null,
        ?string $cod = null,
        ?string $hint = null,
        mixed $raspuns = null,
        ?\Throwable $previous = null,
        public readonly ?int $retryAfterMs = null,
    ) {
        parent::__construct($message, $status, $cod, $hint, $raspuns, $previous);
    }
}
