<?php

declare(strict_types=1);

namespace VerificRca\Exception;

/**
 * Verificarea s-a terminat cu statusul error. `cod` spune de ce:
 *
 * - `VERIFICARE_ESUATA`: sursa oficiala nu a raspuns la timp; reia cu o cerere noua;
 * - `serie_talon_invalida`, `vin_invalid`, `numar_invalid`: sursa a refuzat datele
 *   trimise; corecteaza-le inainte de o cerere noua, altfel rezultatul e acelasi.
 */
final class EroareVerificare extends VerificRcaException
{
    /** Sursa oficiala nu a raspuns la timp. */
    public const VERIFICARE_ESUATA = 'VERIFICARE_ESUATA';

    /** Sursa a respins numarul certificatului de inmatriculare (talon). */
    public const SERIE_TALON_INVALIDA = 'serie_talon_invalida';

    /** Sursa a respins seria de sasiu (VIN). */
    public const VIN_INVALID = 'vin_invalid';

    /** Sursa a respins numarul de inmatriculare. */
    public const NUMAR_INVALID = 'numar_invalid';

    /** True cand sursa a refuzat datele trimise: o reincercare cu aceleasi date nu ajuta. */
    public function dateRespinse(): bool
    {
        return $this->cod !== null && $this->cod !== self::VERIFICARE_ESUATA;
    }
}
