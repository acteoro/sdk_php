<?php

declare(strict_types=1);

namespace Acteo\Exception;

/**
 * 5xx: eroare pe serverul API-ului sau la sursa oficiala. Cererile sigure se
 * reincearca automat. Cand `cod` exista, cererea nu s-a taxat:
 * - `UPSTREAM_UNAVAILABLE` (502), `UPSTREAM_TIMEOUT` (504): sursa oficiala nu a raspuns;
 * - `UPSTREAM_BLOCKED`, `UPSTREAM_CHANGED` (503): sursa e oprita temporar, reia dupa `retryAfterMs`;
 * - `OCUPAT`, `INDISPONIBIL` (503): verificarea nu poate porni acum.
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
