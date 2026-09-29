<?php

declare(strict_types=1);

namespace VerificRca\Exception;

/** 403: operatia nu e permisa pe planul curent (LIMIT_ATINS, ARR_NECESITA_PLAN). */
final class EroareInterzis extends VerificRcaException
{
    /** @param int|null $limita numarul de masini permis de plan, la LIMIT_ATINS */
    public function __construct(
        string $message,
        ?int $status = null,
        ?string $cod = null,
        ?string $hint = null,
        mixed $raspuns = null,
        ?\Throwable $previous = null,
        public readonly ?int $limita = null,
    ) {
        parent::__construct($message, $status, $cod, $hint, $raspuns, $previous);
    }
}
