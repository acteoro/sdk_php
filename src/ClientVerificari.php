<?php

declare(strict_types=1);

namespace Acteo;

use Acteo\Cerere\CerereVerificare;
use Acteo\Cerere\OptiuniAsteptare;
use Acteo\Cerere\ReferintaVerificare;
use Acteo\Enum\TipVerificare;
use Acteo\Exception\EroareTimeoutVerificare;
use Acteo\Exception\EroareVerificare;
use Acteo\Internal\Ceas;
use Acteo\Internal\Decodor;
use Acteo\Internal\Http;
use Acteo\Internal\Raspuns;
use Acteo\Model\Cota;
use Acteo\Model\Limite;
use Acteo\Model\VerificareFinala;
use Acteo\Model\VerificareInCurs;

/**
 * Verificarile la cerere: RCA, ITP, rovinieta si ARR. Il accesezi prin
 * `$vrca->verificari`, nu il construiesti direct.
 *
 * Doar `porneste()` si `verificaSiAsteapta()` consuma din cota planului API.
 * `rezultat()` si `asteapta()` sunt gratuite.
 */
final class ClientVerificari
{
    private const CALE = '/api/public/v1/verificare';
    private const INTERVAL_MINIM_MS = 2000;

    /** @internal */
    public function __construct(
        private readonly Http $http,
        private readonly Ceas $ceas,
    ) {}

    /**
     * Porneste o verificare si consuma o unitate din cota planului.
     *
     * Daca vehiculul a fost verificat de curand, primesti imediat rezultatul
     * existent. Altfel verificarea intra in coada si primesti o `VerificareInCurs`;
     * rezultatul il afli cu `rezultat()`, `asteapta()` sau prin webhook, daca ai
     * trimis `callbackUrl`.
     *
     * Datele sunt validate local inainte de cerere: o greseala de tastare nu
     * pleaca pe retea si nu consuma din cota.
     *
     * @throws Exception\EroareValidare date invalide, local sau HTTP 400
     * @throws Exception\EroareAutentificare cheie API invalida (401)
     * @throws Exception\EroarePlanInactiv contul nu are abonament API activ (402)
     * @throws Exception\EroareLimita cota zilnica sau lunara atinsa (429, cod COTA_ATINSA)
     * @throws EroareVerificare sursa oficiala nu a putut fi interogata
     * @throws Exception\EroareRetea fara raspuns sau timeout
     * @throws Exception\EroareServer eroare 5xx
     */
    public function porneste(CerereVerificare $cerere): VerificareFinala|VerificareInCurs
    {
        $tinta = Validare::valideazaCerereVerificare($cerere);
        $inainte = $this->ceas->acumMs();

        $corp = ['tip' => $tinta->tip->value, 'query' => $tinta->query];
        if ($tinta->serieSasiu !== null) {
            $corp['serieSasiu'] = $tinta->serieSasiu;
        }
        if ($tinta->serieTalon !== null) {
            $corp['serieTalon'] = $tinta->serieTalon;
        }
        if ($tinta->callbackUrl !== null) {
            $corp['callbackUrl'] = $tinta->callbackUrl;
        }

        $raspuns = $this->http->trimite('POST', self::CALE, false, [], $corp);

        return $this->interpreteaza($raspuns, $tinta->serieSasiu, $inainte);
    }

    /**
     * Interogheaza o singura data rezultatul unei verificari. Nu consuma din cota.
     *
     * Serverul accepta cel mult o interogare la 2 secunde pentru aceeasi
     * verificare; la depasire, SDK-ul asteapta cat cere `Retry-After` si reincearca.
     *
     * @param ReferintaVerificare|VerificareInCurs $referinta obiectul primit de la `porneste()` sau o referinta construita
     *
     * @throws EroareVerificare sursa oficiala nu a putut fi interogata; reia cu `porneste()`
     * @throws Exception\EroareAutentificare cheie API invalida (401)
     * @throws Exception\EroareRetea fara raspuns sau timeout, dupa reincercari
     */
    public function rezultat(ReferintaVerificare|VerificareInCurs $referinta): VerificareFinala|VerificareInCurs
    {
        return $this->interogheaza($referinta, null);
    }

    /**
     * Interogheaza periodic pana la rezultatul final. Nu consuma din cota.
     *
     * Implicit o interogare la 3 secunde (minim 2), timp de cel mult 5 minute.
     * Reincercarile se opresc la termen; doar o cerere HTTP deja pornita il poate
     * depasi, cu cel mult timeout-ul ei (`timeoutMs` din constructor).
     *
     * @throws EroareTimeoutVerificare rezultatul nu a venit in `timeoutMs`
     * @throws EroareVerificare sursa oficiala nu a putut fi interogata; reia cu `porneste()`
     * @throws Exception\EroareRetea fara raspuns, dupa reincercari
     */
    public function asteapta(ReferintaVerificare|VerificareInCurs $referinta, ?OptiuniAsteptare $optiuni = null): VerificareFinala
    {
        $optiuni ??= new OptiuniAsteptare();
        $interval = max(self::INTERVAL_MINIM_MS, $optiuni->intervalMs);
        $termen = $this->ceas->acumMs() + $optiuni->timeoutMs;

        while (true) {
            $ramas = $termen - $this->ceas->acumMs();
            if ($ramas <= 0) {
                break;
            }
            $this->ceas->dormiMs(min($interval, $ramas));
            if ($this->ceas->acumMs() >= $termen) {
                break;
            }
            try {
                $raspuns = $this->interogheaza($referinta, $termen);
            } catch (EroareTimeoutVerificare $e) {
                throw self::depasit($referinta, $optiuni, $e->getPrevious());
            }
            if ($raspuns instanceof VerificareFinala) {
                return $raspuns;
            }
        }

        throw self::depasit($referinta, $optiuni, null);
    }

    /**
     * Porneste verificarea si asteapta rezultatul final, intr-un singur apel.
     * Consuma o unitate din cota (doar pornirea; pollingul e gratuit).
     *
     * @throws Exception\EroareValidare date invalide, local sau HTTP 400
     * @throws Exception\EroareLimita cota atinsa (cod COTA_ATINSA)
     * @throws EroareTimeoutVerificare rezultatul nu a venit in `timeoutMs`
     * @throws EroareVerificare sursa oficiala nu a putut fi interogata
     * @throws Exception\ActeoException orice alta eroare de la API sau de retea
     */
    public function verificaSiAsteapta(CerereVerificare $cerere, ?OptiuniAsteptare $optiuni = null): VerificareFinala
    {
        $raspuns = $this->porneste($cerere);

        return $raspuns instanceof VerificareInCurs ? $this->asteapta($raspuns, $optiuni) : $raspuns;
    }

    private function interogheaza(ReferintaVerificare|VerificareInCurs $referinta, ?int $termenMs): VerificareFinala|VerificareInCurs
    {
        $raspuns = $this->http->trimite('GET', self::CALE, true, [
            'tip' => $referinta->tip->value,
            'query' => $referinta->query,
            'serieSasiu' => $referinta->tip === TipVerificare::Rovinieta ? $referinta->serieSasiu : null,
            'since' => $referinta->since !== null ? (string) $referinta->since : null,
        ], null, $termenMs);

        return $this->interpreteaza($raspuns, $referinta->serieSasiu, $referinta->since);
    }

    private static function depasit(ReferintaVerificare|VerificareInCurs $referinta, OptiuniAsteptare $optiuni, ?\Throwable $cauza): EroareTimeoutVerificare
    {
        return new EroareTimeoutVerificare(
            "Verificarea {$referinta->tip->value} pentru {$referinta->query} nu s-a finalizat in "
            . (int) round($optiuni->timeoutMs / 1000) . ' secunde.',
            cod: 'TIMEOUT_VERIFICARE',
            previous: $cauza,
        );
    }

    private function interpreteaza(Raspuns $raspuns, ?string $serieSasiu, ?int $sinceImplicit): VerificareFinala|VerificareInCurs
    {
        $limite = self::limite($raspuns);

        return Http::decodeaza($raspuns, function (array $d) use ($serieSasiu, $sinceImplicit, $limite): VerificareFinala|VerificareInCurs {
            $status = $d['status'] ?? null;
            if ($status === 'error') {
                Decodor::enum($d, 'tip', '', TipVerificare::class);
                // Cu `cod`, sursa a refuzat datele trimise si o reincercare nu ajuta.
                throw new EroareVerificare(
                    Decodor::sir($d, 'error', ''),
                    cod: 'VERIFICARE_ESUATA',
                    hint: Decodor::sirOptional($d, 'hint', ''),
                    raspuns: $d,
                    codSursa: Decodor::sirOptional($d, 'cod', ''),
                );
            }
            if ($status === VerificareInCurs::STATUS) {
                $tip = Decodor::enum($d, 'tip', '', TipVerificare::class);

                return new VerificareInCurs(
                    tip: $tip,
                    query: Decodor::sir($d, 'query', ''),
                    serieSasiu: $tip === TipVerificare::Rovinieta ? $serieSasiu : null,
                    since: self::sinceDinPollUrl(Decodor::sirOptional($d, 'pollUrl', '')) ?? $sinceImplicit ?? $this->ceas->acumMs(),
                    requestId: Decodor::sirOptional($d, 'requestId', ''),
                    queuedAt: Decodor::dataOptionala($d, 'queuedAt', ''),
                    hint: Decodor::sirOptional($d, 'hint', ''),
                    limite: $limite,
                );
            }

            return VerificareFinala::fromArray($d, '', $limite);
        });
    }

    /** Cat mai ai din cota, din headerele `X-RateLimit-*`; null daca lipseste vreunul. */
    private static function limite(Raspuns $raspuns): ?Limite
    {
        $valori = [];
        foreach (['x-ratelimit-limit-day', 'x-ratelimit-remaining-day', 'x-ratelimit-limit-month', 'x-ratelimit-remaining-month'] as $nume) {
            $valoare = $raspuns->header($nume);
            if ($valoare === null || !is_numeric($valoare)) {
                return null;
            }
            $valori[] = (int) $valoare;
        }

        return new Limite(new Cota($valori[0], $valori[1]), new Cota($valori[2], $valori[3]));
    }

    /** Citeste `since` din `pollUrl`, ca pollingul sa foloseasca ceasul serverului. */
    private static function sinceDinPollUrl(?string $pollUrl): ?int
    {
        $intrebare = $pollUrl === null ? false : strpos($pollUrl, '?');
        if ($pollUrl === null || $intrebare === false) {
            return null;
        }
        parse_str(substr($pollUrl, $intrebare + 1), $parametri);
        $since = $parametri['since'] ?? null;

        return is_string($since) && ctype_digit($since) && (int) $since > 0 ? (int) $since : null;
    }
}
