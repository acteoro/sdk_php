<?php

declare(strict_types=1);

namespace Acteo\Exception;

/**
 * 429: limita a fost atinsa. `cod` spune care:
 * - `DAILY_LIMIT_REACHED`: limita zilnica a planului; se reseteaza la miezul noptii,
 *   ora Romaniei, iar `retryAfterMs` spune cat mai e;
 * - `PLAN_LIMIT_REACHED`: limita lunara, sau plafonul consumului peste plan;
 * - `SOLD_INSUFICIENT`: soldul preplatit nu acopera cererile peste plan;
 * - `PLAFON_NETAXAT`: prea multe raspunsuri netaxate azi (decodari, poze generice);
 * - `LIMITA_FLOTA_ATINSA`: plafonul zilnic de adaugari/stergeri de vehicule;
 * - `PREA_MULTE_CERERI`: polling prea des (SDK-ul reincearca singur);
 * - `COTA_ATINSA`: cota atinsa, de la un server mai vechi care nu trimite cod.
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
