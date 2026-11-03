<?php

declare(strict_types=1);

namespace Acteo\Auto;

use Acteo\Cerere\CerereVerificare;
use Acteo\Cerere\OptiuniAsteptare;
use Acteo\Enum\TipVerificare;
use Acteo\Model\VerificareFinala;
use Acteo\Model\VerificareInCurs;

/**
 * Copia conforma ARR. Accepta doar numarul de inmatriculare: ARR nu cauta
 * dupa VIN. Il accesezi prin `$acteo->auto->arr`.
 *
 * `verifica()` si `porneste()` consuma din cota planului; `rezultat()` si
 * `asteapta()` sunt gratuite.
 */
final class ClientArr extends ClientDocument
{
    public function tip(): TipVerificare
    {
        return TipVerificare::Arr;
    }

    /**
     * Verifica copia conforma ARR si asteapta rezultatul final de la sursa oficiala.
     *
     * @param string $numar numarul de inmatriculare, ex: `B123ABC`
     *
     * @throws \Acteo\Exception\EroareValidare numar de inmatriculare invalid (inclusiv un VIN), local
     * @throws \Acteo\Exception\EroareLimita cota atinsa (DAILY_LIMIT_REACHED, PLAN_LIMIT_REACHED)
     * @throws \Acteo\Exception\EroareServer sursa oficiala oprita temporar (UPSTREAM_BLOCKED, UPSTREAM_CHANGED); netaxat
     * @throws \Acteo\Exception\EroareVerificare sursa oficiala nu a dat un raspuns sau a refuzat datele; motivul e in `cod`
     * @throws \Acteo\Exception\EroareTimeoutVerificare rezultatul nu a venit in `timeoutMs`
     */
    public function verifica(string $numar, ?OptiuniAsteptare $optiuni = null): VerificareFinala
    {
        return $this->verificaCerere(self::cerere($numar), $optiuni);
    }

    /**
     * Porneste verificarea fara sa astepte.
     *
     * @param string|null $callbackUrl URL http(s) unde API-ul trimite rezultatul prin POST cand e gata
     *
     * @throws \Acteo\Exception\EroareLimita cota atinsa (DAILY_LIMIT_REACHED, PLAN_LIMIT_REACHED)
     * @throws \Acteo\Exception\EroareServer sursa oficiala oprita temporar (UPSTREAM_BLOCKED, UPSTREAM_CHANGED); netaxat
     */
    public function porneste(string $numar, ?string $callbackUrl = null): VerificareFinala|VerificareInCurs
    {
        return $this->pornesteCerere(self::cerere($numar, $callbackUrl));
    }

    /**
     * Interogheaza o singura data rezultatul. Nu consuma din cota.
     *
     * @param VerificareInCurs|string $referinta verificarea primita de la `porneste()` sau numarul
     */
    public function rezultat(VerificareInCurs|string $referinta): VerificareFinala|VerificareInCurs
    {
        return $this->rezultatPentru($referinta instanceof VerificareInCurs ? $referinta : self::cerere($referinta));
    }

    /**
     * Interogheaza periodic pana la rezultatul final. Nu consuma din cota.
     *
     * @param VerificareInCurs|string $referinta verificarea primita de la `porneste()` sau numarul
     *
     * @throws \Acteo\Exception\EroareTimeoutVerificare rezultatul nu a venit in `timeoutMs`
     */
    public function asteapta(VerificareInCurs|string $referinta, ?OptiuniAsteptare $optiuni = null): VerificareFinala
    {
        return $this->asteaptaPentru($referinta instanceof VerificareInCurs ? $referinta : self::cerere($referinta), $optiuni);
    }

    private static function cerere(string $numar, ?string $callbackUrl = null): CerereVerificare
    {
        return new CerereVerificare(TipVerificare::Arr, $numar, null, $callbackUrl);
    }
}
