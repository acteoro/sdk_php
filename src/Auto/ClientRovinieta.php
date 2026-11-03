<?php

declare(strict_types=1);

namespace Acteo\Auto;

use Acteo\Cerere\CerereVerificare;
use Acteo\Cerere\OptiuniAsteptare;
use Acteo\Enum\TipVerificare;
use Acteo\Model\VerificareFinala;
use Acteo\Model\VerificareInCurs;

/**
 * Rovinieta, din CNAIR. Cere numarul de inmatriculare, VIN-ul si numarul
 * certificatului de inmatriculare (talon). Il accesezi prin `$acteo->auto->rovinieta`.
 *
 * Identificatorul: `['numar' => 'B123ABC', 'vin' => 'WVWZZZ1JZXW000001', 'serieTalon' => 'I00639603F']`
 * sau `new IdentificatorRovinieta(...)`.
 *
 * `verifica()` si `porneste()` consuma din cota planului; `rezultat()` si
 * `asteapta()` sunt gratuite.
 */
final class ClientRovinieta extends ClientDocument
{
    public function tip(): TipVerificare
    {
        return TipVerificare::Rovinieta;
    }

    /**
     * Verifica rovinieta si asteapta rezultatul final de la sursa oficiala.
     *
     * @param array{numar?: string, vin?: string, serieTalon?: string}|IdentificatorRovinieta $identificator
     *
     * @throws \Acteo\Exception\EroareValidare lipseste sau e invalid unul dintre identificatori, local
     * @throws \Acteo\Exception\EroareLimita cota atinsa (DAILY_LIMIT_REACHED, PLAN_LIMIT_REACHED)
     * @throws \Acteo\Exception\EroareServer sursa oficiala oprita temporar (UPSTREAM_BLOCKED, UPSTREAM_CHANGED); netaxat
     * @throws \Acteo\Exception\EroareVerificare sursa oficiala nu a dat un raspuns sau a refuzat datele; motivul e in `cod`
     * @throws \Acteo\Exception\EroareTimeoutVerificare rezultatul nu a venit in `timeoutMs`
     */
    public function verifica(array|IdentificatorRovinieta $identificator, ?OptiuniAsteptare $optiuni = null): VerificareFinala
    {
        return $this->verificaCerere(self::cerere($identificator), $optiuni);
    }

    /**
     * Porneste verificarea fara sa astepte.
     *
     * @param array{numar?: string, vin?: string, serieTalon?: string}|IdentificatorRovinieta $identificator
     * @param string|null $callbackUrl URL http(s) unde API-ul trimite rezultatul prin POST cand e gata
     *
     * @throws \Acteo\Exception\EroareLimita cota atinsa (DAILY_LIMIT_REACHED, PLAN_LIMIT_REACHED)
     * @throws \Acteo\Exception\EroareServer sursa oficiala oprita temporar (UPSTREAM_BLOCKED, UPSTREAM_CHANGED); netaxat
     */
    public function porneste(array|IdentificatorRovinieta $identificator, ?string $callbackUrl = null): VerificareFinala|VerificareInCurs
    {
        return $this->pornesteCerere(self::cerere($identificator, $callbackUrl));
    }

    /**
     * Interogheaza o singura data rezultatul. Nu consuma din cota.
     *
     * @param VerificareInCurs|array{numar?: string, vin?: string, serieTalon?: string}|IdentificatorRovinieta $referinta
     *   verificarea primita de la `porneste()` sau identificatorul
     */
    public function rezultat(VerificareInCurs|array|IdentificatorRovinieta $referinta): VerificareFinala|VerificareInCurs
    {
        return $this->rezultatPentru($referinta instanceof VerificareInCurs ? $referinta : self::cerere($referinta));
    }

    /**
     * Interogheaza periodic pana la rezultatul final. Nu consuma din cota.
     *
     * @param VerificareInCurs|array{numar?: string, vin?: string, serieTalon?: string}|IdentificatorRovinieta $referinta
     *
     * @throws \Acteo\Exception\EroareTimeoutVerificare rezultatul nu a venit in `timeoutMs`
     */
    public function asteapta(VerificareInCurs|array|IdentificatorRovinieta $referinta, ?OptiuniAsteptare $optiuni = null): VerificareFinala
    {
        return $this->asteaptaPentru($referinta instanceof VerificareInCurs ? $referinta : self::cerere($referinta), $optiuni);
    }

    /** @param array{numar?: string, vin?: string, serieTalon?: string}|IdentificatorRovinieta $identificator */
    private static function cerere(array|IdentificatorRovinieta $identificator, ?string $callbackUrl = null): CerereVerificare
    {
        $id = IdentificatorRovinieta::din($identificator);

        return new CerereVerificare(TipVerificare::Rovinieta, $id->numar, $id->vin, $callbackUrl, $id->serieTalon);
    }
}
