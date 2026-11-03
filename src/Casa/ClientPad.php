<?php

declare(strict_types=1);

namespace Acteo\Casa;

use Acteo\Cerere\CererePolitaPad;
use Acteo\Cerere\OptiuniAsteptare;
use Acteo\Enum\StatusPolitaPad;
use Acteo\Exception\EroareTimeoutVerificare;
use Acteo\Exception\EroareValidare;
use Acteo\Exception\EroareVerificare;
use Acteo\Internal\Ceas;
use Acteo\Internal\Decodor;
use Acteo\Internal\Http;
use Acteo\Model\PolitaPadPornita;
use Acteo\Model\RezultatPolitaPad;
use Acteo\Validare;

/**
 * Verificarea unei polite PAD (asigurarea obligatorie a locuintei) dupa CNP-ul
 * proprietarului si localitatea locuintei, la PADROM. Cere abonamentul API
 * Verificare polita PAD si acordul proprietarului (`acord: true`). Il accesezi
 * prin `$acteo->casa->pad`.
 *
 * Verificarea e asincrona si poate dura cateva minute. `verifica()` si
 * `porneste()` se taxeaza doar cand PADROM raspunde; `rezultat()` si
 * `asteapta()` sunt gratuite. CNP-ul nu apare in raspunsuri, doar `cnpMascat`.
 */
final class ClientPad
{
    private const CALE = '/api/public/v1/polita-pad';
    private const INTERVAL_MINIM_MS = 2000;

    /** @internal */
    public function __construct(
        private readonly Http $http,
        private readonly Ceas $ceas,
    ) {}

    /**
     * Verifica polita si asteapta raspunsul PADROM.
     *
     * Exemplu:
     *     $r = $acteo->casa->pad->verifica(['cnp' => $cnp, 'judet' => 'Cluj', 'localitate' => 'Cluj-Napoca', 'acord' => true]);
     *     echo $r->valabila ? 'Are polita PAD' : 'Fara polita PAD valabila';
     *
     * @param array{cnp?: string, judet?: string, localitate?: string, sector?: int, judetId?: string, localitateId?: string, data?: string, acord?: bool}|CererePolitaPad $cerere
     *
     * @return RezultatPolitaPad verdictul, cu status `Done`
     *
     * @throws EroareValidare CNP, acord sau data invalide (local), ori judet sau localitate negasite (HTTP 422, netaxat)
     * @throws \Acteo\Exception\EroarePlanInactiv lipseste abonamentul de verificare PAD
     * @throws \Acteo\Exception\EroareLimita cota atinsa sau prea multe verificari in curs (OCUPAT)
     * @throws EroareVerificare PADROM a refuzat datele (INVALID_IDENTIFIER) sau nu a raspuns; motivul e in `cod`
     * @throws EroareTimeoutVerificare raspunsul nu a venit in `timeoutMs`
     */
    public function verifica(array|CererePolitaPad $cerere, ?OptiuniAsteptare $optiuni = null): RezultatPolitaPad
    {
        return $this->asteapta($this->porneste($cerere), $optiuni);
    }

    /**
     * Porneste verificarea fara sa astepte. Afli raspunsul cu `asteapta()`,
     * `rezultat()` sau prin webhook la `callbackUrl`.
     *
     * @param array{cnp?: string, judet?: string, localitate?: string, sector?: int, judetId?: string, localitateId?: string, data?: string, acord?: bool}|CererePolitaPad $cerere
     * @param string|null $callbackUrl URL http(s) unde API-ul trimite rezultatul prin POST cand e gata
     *
     * @throws EroareValidare date invalide, local sau HTTP 400/422
     * @throws \Acteo\Exception\EroarePlanInactiv lipseste abonamentul de verificare PAD
     * @throws \Acteo\Exception\EroareLimita cota atinsa sau prea multe verificari in curs (OCUPAT)
     * @throws \Acteo\Exception\EroareServer PADROM e oprit temporar (UPSTREAM_BLOCKED, UPSTREAM_CHANGED) sau INDISPONIBIL; netaxat
     */
    public function porneste(array|CererePolitaPad $cerere, ?string $callbackUrl = null): PolitaPadPornita
    {
        $corp = Validare::valideazaCererePolitaPad(CererePolitaPad::din($cerere), $callbackUrl);
        $raspuns = $this->http->trimite('POST', self::CALE, false, [], $corp);
        $limite = $raspuns->limite();

        return Http::decodeaza($raspuns, static fn(array $d): PolitaPadPornita => PolitaPadPornita::fromArray($d, $limite));
    }

    /**
     * Interogheaza o singura data rezultatul. Nu consuma din cota.
     *
     * @param PolitaPadPornita|string $referinta raspunsul de la `porneste()` sau `requestId`
     *
     * @return RezultatPolitaPad verdictul (`Done`) sau cererea inca in curs (`Processing`)
     *
     * @throws EroareVerificare verificarea s-a incheiat cu eroare; motivul e in `cod`
     * @throws \Acteo\Exception\EroareNegasit cererea nu exista in contul cheii sau a trecut de 30 de zile (NEGASIT)
     */
    public function rezultat(PolitaPadPornita|string $referinta): RezultatPolitaPad
    {
        return $this->interogheaza(self::id($referinta), null);
    }

    /**
     * Interogheaza periodic pana la raspunsul PADROM. Nu consuma din cota.
     *
     * Implicit o interogare la 3 secunde (minim 2), timp de cel mult 5 minute.
     *
     * @param PolitaPadPornita|string $referinta raspunsul de la `porneste()` sau `requestId`
     *
     * @return RezultatPolitaPad verdictul, cu status `Done`
     *
     * @throws EroareVerificare verificarea s-a incheiat cu eroare; motivul e in `cod`
     * @throws EroareTimeoutVerificare raspunsul nu a venit in `timeoutMs`
     */
    public function asteapta(PolitaPadPornita|string $referinta, ?OptiuniAsteptare $optiuni = null): RezultatPolitaPad
    {
        $id = self::id($referinta);
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
                $raspuns = $this->interogheaza($id, $termen);
            } catch (EroareTimeoutVerificare $e) {
                throw self::depasit($id, $optiuni, $e->getPrevious());
            }
            if ($raspuns->status === StatusPolitaPad::Done) {
                return $raspuns;
            }
        }

        throw self::depasit($id, $optiuni, null);
    }

    private function interogheaza(string $id, ?int $termenMs): RezultatPolitaPad
    {
        $raspuns = $this->http->trimite('GET', self::CALE, true, ['id' => $id], null, $termenMs);

        return Http::decodeaza($raspuns, static function (array $d): RezultatPolitaPad {
            if (($d['status'] ?? null) === StatusPolitaPad::Error->value) {
                throw new EroareVerificare(
                    Decodor::sir($d, 'error', ''),
                    cod: Decodor::sirOptional($d, 'code', '') ?? 'VERIFICARE_ESUATA',
                    raspuns: $d,
                );
            }

            return RezultatPolitaPad::fromArray($d);
        });
    }

    private static function id(PolitaPadPornita|string $referinta): string
    {
        $id = trim($referinta instanceof PolitaPadPornita ? $referinta->requestId : $referinta);
        if ($id === '') {
            throw new EroareValidare('Lipseste `requestId` primit de la porneste().', cod: 'VALIDARE_LOCALA', camp: 'requestId');
        }

        return $id;
    }

    private static function depasit(string $id, OptiuniAsteptare $optiuni, ?\Throwable $cauza): EroareTimeoutVerificare
    {
        return new EroareTimeoutVerificare(
            "Verificarea politei PAD {$id} nu s-a finalizat in " . (int) round($optiuni->timeoutMs / 1000) . ' secunde.',
            cod: 'TIMEOUT_VERIFICARE',
            previous: $cauza,
        );
    }
}
