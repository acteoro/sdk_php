<?php

declare(strict_types=1);

namespace VerificRca\Exception;

/** Cererea nu a primit raspuns: retea cazuta, DNS, conexiune refuzata sau timeout. */
final class EroareRetea extends VerificRcaException
{
    /**
     * @param bool $timeout adevarat cand cererea a depasit timpul de raspuns
     * @param bool $neajunsa adevarat cand e sigur ca cererea nu a ajuns la server (DNS,
     *   conexiune refuzata), deci se poate repeta fara risc chiar si pentru un POST
     */
    public function __construct(
        string $message,
        ?int $status = null,
        ?string $cod = null,
        ?string $hint = null,
        mixed $raspuns = null,
        ?\Throwable $previous = null,
        public readonly bool $timeout = false,
        public readonly bool $neajunsa = false,
    ) {
        parent::__construct($message, $status, $cod, $hint, $raspuns, $previous);
    }
}
