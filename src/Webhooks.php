<?php

declare(strict_types=1);

namespace Acteo;

use Acteo\Exception\EroareWebhook;
use Acteo\Internal\FormaInvalida;
use Acteo\Model\WebhookAsigurareSchimbare;
use Acteo\Model\WebhookDocumentExpira;
use Acteo\Model\WebhookDosarEveniment;
use Acteo\Model\WebhookDosarTermen;
use Acteo\Model\WebhookFirmaDosar;
use Acteo\Model\WebhookFirmaSchimbare;
use Acteo\Model\WebhookMarcaExpira;
use Acteo\Model\WebhookMarcaSchimbare;
use Acteo\Model\WebhookNotificare;
use Acteo\Model\WebhookRezultat;
use Acteo\Model\WebhookTermenExpira;
use Acteo\Model\WebhookTest;
use Psr\Http\Message\ServerRequestInterface;

/** Citirea webhook-urilor trimise de API. Nu face cereri pe retea. */
final class Webhooks
{
    /** Headerul cu cheia de webhook a contului (`whsec_...`). */
    public const HEADER_CHEIE = 'x-acteo-key';

    /** Headerul cu numele evenimentului (`document.expira`, `webhook.test` etc.). */
    public const HEADER_EVENIMENT = 'x-acteo-event';

    // Numele vechi al headerului cu cheia, citit doar cand lipseste cel nou.
    private const HEADER_CHEIE_VECHI = 'x-verificrca-key';

    /** Clasa fiecarui eveniment de notificare, dupa `event`. */
    private const EVENIMENTE = [
        WebhookDocumentExpira::EVENIMENT => WebhookDocumentExpira::class,
        WebhookDosarEveniment::EVENIMENT => WebhookDosarEveniment::class,
        WebhookDosarTermen::EVENIMENT => WebhookDosarTermen::class,
        WebhookFirmaSchimbare::EVENIMENT => WebhookFirmaSchimbare::class,
        WebhookFirmaDosar::EVENIMENT => WebhookFirmaDosar::class,
        WebhookMarcaSchimbare::EVENIMENT => WebhookMarcaSchimbare::class,
        WebhookMarcaExpira::EVENIMENT => WebhookMarcaExpira::class,
        WebhookAsigurareSchimbare::EVENIMENT => WebhookAsigurareSchimbare::class,
        WebhookTermenExpira::EVENIMENT => WebhookTermenExpira::class,
        WebhookTest::EVENIMENT => WebhookTest::class,
    ];

    private function __construct() {}

    /**
     * Citeste rezultatul trimis la `callbackUrl` dupa o verificare.
     *
     * Foloseste un `callbackUrl` pe HTTPS, greu de ghicit (de exemplu cu un token
     * secret in cale), si potriveste `requestId` cu cel primit la `porneste()`
     * inainte sa ai incredere in continut.
     *
     * @param string|array<mixed> $corp corpul cererii (`file_get_contents('php://input')`) sau JSON-ul deja decodat
     *
     * @throws EroareWebhook corpul nu e JSON (JSON_INVALID) sau nu e un rezultat valid (PAYLOAD_INVALID)
     */
    public static function parseazaWebhookRezultat(string|array $corp): WebhookRezultat
    {
        $date = self::parseazaCorp($corp);

        return self::decodeaza($date, static fn(array $d): WebhookRezultat => WebhookRezultat::fromArray($d));
    }

    /**
     * Verifica si citeste o notificare trimisa la URL-ul de webhook al contului:
     * expirarea unui document (`document.expira`), evenimentele celorlalte
     * servicii (`dosar.*`, `firma.*`, `marca.*`, `asigurare.schimbare`,
     * `termen.expira`) sau testul din dashboard (`webhook.test`).
     *
     * Cheia vine in headerul `X-Acteo-Key` si e comparata in timp constant cu
     * cea din dashboard. Pastreaza URL-ul pe HTTPS.
     *
     * @param string|array<mixed> $corp corpul cererii sau JSON-ul deja decodat
     * @param array<mixed>|ServerRequestInterface $headere `getallheaders()`, `$_SERVER`,
     *   `$request->headers->all()` din Laravel/Symfony sau o cerere PSR-7
     * @param string $cheie cheia de webhook din dashboard (`whsec_...`)
     *
     * @return WebhookDocumentExpira|WebhookDosarEveniment|WebhookDosarTermen|WebhookFirmaSchimbare|WebhookFirmaDosar|WebhookMarcaSchimbare|WebhookMarcaExpira|WebhookAsigurareSchimbare|WebhookTermenExpira|WebhookTest
     *
     * @throws EroareWebhook cheia lipseste din configurare (CHEIE_NECONFIGURATA), din header sau nu se
     *   potriveste (CHEIE_INVALIDA), ori payloadul e invalid (JSON_INVALID, PAYLOAD_INVALID)
     */
    public static function verificaWebhookNotificare(string|array $corp, array|ServerRequestInterface $headere, string $cheie): WebhookNotificare
    {
        if ($cheie === '') {
            throw new EroareWebhook('Lipseste cheia de webhook din configurare.', cod: 'CHEIE_NECONFIGURATA');
        }
        $primita = self::citesteHeader($headere, self::HEADER_CHEIE) ?? self::citesteHeader($headere, self::HEADER_CHEIE_VECHI);
        if ($primita === null || !hash_equals($cheie, $primita)) {
            throw new EroareWebhook('Cheia din headerul X-Acteo-Key lipseste sau nu se potriveste.', cod: 'CHEIE_INVALIDA');
        }

        $date = self::parseazaCorp($corp);

        return self::decodeaza($date, static function (array $d): WebhookNotificare {
            $event = $d['event'] ?? null;
            $clasa = is_string($event) ? (self::EVENIMENTE[$event] ?? null) : null;
            if ($clasa === null) {
                throw new FormaInvalida('event: se astepta unul dintre "' . implode('", "', array_keys(self::EVENIMENTE)) . '"');
            }

            return $clasa::fromArray($d);
        });
    }

    /**
     * @param string|array<mixed> $corp
     *
     * @return array<mixed>
     */
    private static function parseazaCorp(string|array $corp): array
    {
        if (is_array($corp)) {
            return $corp;
        }
        try {
            $date = json_decode($corp, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new EroareWebhook('Corpul webhook-ului nu e JSON valid.', cod: 'JSON_INVALID', raspuns: $corp, previous: $e);
        }
        if (!is_array($date)) {
            throw new EroareWebhook('Payload webhook invalid: se astepta un obiect JSON.', cod: 'PAYLOAD_INVALID', raspuns: $date);
        }

        return $date;
    }

    /**
     * @template T
     *
     * @param array<mixed> $date
     * @param callable(array<mixed>): T $decodor
     *
     * @return T
     */
    private static function decodeaza(array $date, callable $decodor): mixed
    {
        try {
            return $decodor($date);
        } catch (FormaInvalida $e) {
            throw new EroareWebhook('Payload webhook invalid: ' . $e->getMessage(), cod: 'PAYLOAD_INVALID', raspuns: $date, previous: $e);
        }
    }

    /** @param array<mixed>|ServerRequestInterface $headere */
    private static function citesteHeader(array|ServerRequestInterface $headere, string $nume): ?string
    {
        if ($headere instanceof ServerRequestInterface) {
            $valoare = $headere->getHeaderLine($nume);

            return $valoare === '' ? null : $valoare;
        }
        foreach ($headere as $cheie => $valoare) {
            $normalizat = str_replace('_', '-', strtolower((string) $cheie));
            if (str_starts_with($normalizat, 'http-')) {
                $normalizat = substr($normalizat, 5);
            }
            if ($normalizat !== $nume) {
                continue;
            }
            if (is_array($valoare)) {
                $valoare = reset($valoare);
            }

            return is_string($valoare) && $valoare !== '' ? $valoare : null;
        }

        return null;
    }
}
