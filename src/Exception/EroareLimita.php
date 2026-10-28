<?php

declare(strict_types=1);

namespace Acteo\Exception;

/**
 * 429: limita a fost atinsa. `cod` spune care:
 * - `COTA_ATINSA`: cota zilnica sau lunara de verificari a planului;
 * - `LIMITA_FLOTA_ATINSA`: plafonul zilnic de adaugari/stergeri de vehicule;
 * - `PREA_MULTE_CERERI`: polling prea des (SDK-ul reincearca singur).
 */
final class EroareLimita extends ActeoException
{
    /**
     * @param int|null $retryAfterMs cat sa astepti inainte de o cerere noua, din headerul Retry-After
     * @param string|null $utilizareZi consumul zilnic, ex: "300/300"
     * @param string|null $utilizareLuna consumul lunar, ex: "4120/10000"
     */
    public function __construct(
        string $message,
        ?int $status = null,
        ?string $cod = null,
        ?string $hint = null,
        mixed $raspuns = null,
        ?\Throwable $previous = null,
        public readonly ?int $retryAfterMs = null,
        public readonly ?string $utilizareZi = null,
        public readonly ?string $utilizareLuna = null,
    ) {
        parent::__construct($message, $status, $cod, $hint, $raspuns, $previous);
    }
}
