<?php

declare(strict_types=1);

namespace VerificRca\Exception;

/**
 * Datele trimise sunt invalide. Apare fie local, inainte de orice cerere (nu se
 * consuma nimic din cota), fie ca raspuns 400 de la API.
 */
final class EroareValidare extends VerificRcaException
{
    /**
     * @param list<string>|null $acceptate valorile acceptate, cand API-ul le trimite (ex: tipurile de verificare)
     * @param string|null $camp campul cu problema, cand eroarea e detectata local
     */
    public function __construct(
        string $message,
        ?int $status = null,
        ?string $cod = null,
        ?string $hint = null,
        mixed $raspuns = null,
        ?\Throwable $previous = null,
        public readonly ?array $acceptate = null,
        public readonly ?string $camp = null,
    ) {
        parent::__construct($message, $status, $cod, $hint, $raspuns, $previous);
    }
}
