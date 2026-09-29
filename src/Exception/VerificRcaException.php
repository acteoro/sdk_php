<?php

declare(strict_types=1);

namespace VerificRca\Exception;

/**
 * Baza tuturor erorilor aruncate de SDK. `catch (VerificRcaException $e)` prinde
 * orice eroare de la verificrca; subclasa spune exact care e.
 */
class VerificRcaException extends \RuntimeException
{
    /**
     * @param string $message mesajul, in romana, exact cum vine de la API cand exista
     * @param int|null $status statusul HTTP, cand eroarea vine dintr-un raspuns
     * @param string|null $cod codul masinii (`LIMIT_ATINS`, `VEHICUL_EXISTENT`, `NEGASIT` etc.)
     * @param string|null $hint indicatia API-ului despre cum se rezolva problema
     * @param mixed $raspuns corpul brut al raspunsului, pentru depanare
     * @param \Throwable|null $previous eroarea originala (de retea, de parsare)
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $cod = null,
        public readonly ?string $hint = null,
        public readonly mixed $raspuns = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
