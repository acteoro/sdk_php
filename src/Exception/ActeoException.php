<?php

declare(strict_types=1);

namespace Acteo\Exception;

/**
 * Baza tuturor erorilor aruncate de SDK. `catch (ActeoException $e)` prinde
 * orice eroare de la API; subclasa spune exact care e. Vechiul nume
 * `VerificRca\Exception\VerificRcaException` e un alias al acestei clase.
 */
class ActeoException extends \RuntimeException
{
    /**
     * @param string $message mesajul, in romana, exact cum vine de la API cand exista
     * @param int|null $status statusul HTTP, cand eroarea vine dintr-un raspuns
     * @param string|null $cod codul masinii; de la API vine unul din `CodEroare` (`DAILY_LIMIT_REACHED`,
     *   `UPSTREAM_TIMEOUT`, `VEHICUL_EXISTENT` etc.), iar SDK-ul pune singur cateva coduri locale
     *   (`VALIDARE_LOCALA`, `TIMEOUT_VERIFICARE`, `PREA_MULTE_CERERI`)
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
