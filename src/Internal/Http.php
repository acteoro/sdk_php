<?php

declare(strict_types=1);

namespace Acteo\Internal;

use Acteo\Exception\ActeoException;
use Acteo\Exception\EroareLimita;
use Acteo\Exception\EroareRaspunsInvalid;
use Acteo\Exception\EroareRetea;
use Acteo\Exception\EroareServer;
use Acteo\Exception\EroareTimeoutVerificare;
use Acteo\Exception\EroareValidare;
use Psr\Http\Client\ClientExceptionInterface;

/** @internal Trimite cereri catre API, cu maparea erorilor si retry. */
final class Http
{
    /** Coduri cURL care garanteaza ca cererea nu a plecat spre server: DNS, conexiune refuzata. */
    private const ERRNO_NEAJUNSA = [6, 7];
    private const ERRNO_TIMEOUT = 28;

    public function __construct(private readonly Setari $s) {}

    /**
     * Trimite o cerere cu politica de retry din setari.
     *
     * @param bool $idempotenta cererea poate fi repetata fara efecte in plus (GET, DELETE)
     * @param array<string, string|null> $query valorile null nu se trimit
     * @param array<string, mixed>|null $corp trimis ca JSON
     * @param int|null $termenMs momentul (ms) dupa care nu mai reincercam; o pauza care l-ar
     *   depasi opreste cererea cu `EroareTimeoutVerificare`
     */
    public function trimite(string $metoda, string $cale, bool $idempotenta, array $query = [], ?array $corp = null, ?int $termenMs = null): Raspuns
    {
        $incercare = 0;
        while (true) {
            try {
                return $this->trimiteOData($metoda, $cale, $query, $corp);
            } catch (EroareRetea|EroareServer|EroareLimita $e) {
                $retry = $this->s->retry;
                if ($retry === null || $incercare >= $retry->incercari || !self::sePoateReincerca($idempotenta, $e, $retry)) {
                    throw $e;
                }
                $pauza = self::pauza($retry, $incercare, $e);
                if ($termenMs !== null && $this->s->ceas->acumMs() + $pauza >= $termenMs) {
                    throw new EroareTimeoutVerificare('Termenul de asteptare a expirat in timpul reincercarilor.', cod: 'TIMEOUT_VERIFICARE', previous: $e);
                }
                $this->s->ceas->dormiMs($pauza);
                ++$incercare;
            }
        }
    }

    /**
     * Regula de baza: nu repetam o cerere neidempotenta care ar fi putut ajunge la
     * server, pentru ca o verificare se taxeaza.
     */
    public static function sePoateReincerca(bool $idempotenta, ActeoException $e, SetariRetry $retry): bool
    {
        if ($e instanceof EroareRetea) {
            return $idempotenta || $e->neajunsa;
        }
        if ($e instanceof EroareServer) {
            // 502/503/504 vin de la proxy: cererea nu a ajuns la aplicatie.
            return $idempotenta || in_array($e->status, [502, 503, 504], true);
        }
        if ($e instanceof EroareLimita) {
            // Doar throttle-ul de polling e trecator; cota si plafonul de flota nu se reseteaza in secunde.
            return $idempotenta
                && $e->cod === 'PREA_MULTE_CERERI'
                && ($e->retryAfterMs ?? 0) <= $retry->intarziereMaximaMs;
        }

        return false;
    }

    /** Backoff exponential cu jitter, plafonat, dar niciodata sub `Retry-After`. */
    public static function pauza(SetariRetry $retry, int $incercare, ActeoException $e): int
    {
        $baza = min($retry->intarziereInitialaMs * 2 ** min($incercare, 30), $retry->intarziereMaximaMs);
        $jitter = 0.8 + mt_rand() / mt_getrandmax() * 0.4;
        $pauza = (int) min(round($baza * $jitter), $retry->intarziereMaximaMs);
        if ($e instanceof EroareLimita && $e->retryAfterMs !== null) {
            $pauza = max($pauza, $e->retryAfterMs);
        }

        return $pauza;
    }

    /**
     * Decodeaza corpul unui raspuns; o nepotrivire devine `EroareRaspunsInvalid`.
     *
     * @template T
     *
     * @param callable(array<mixed>): T $decodor
     *
     * @return T
     */
    public static function decodeaza(Raspuns $raspuns, callable $decodor): mixed
    {
        if (!is_array($raspuns->corp)) {
            throw new EroareRaspunsInvalid(
                'Raspunsul API-ului nu are forma asteptata: corpul nu e un obiect JSON.',
                status: $raspuns->status,
                raspuns: $raspuns->corp,
            );
        }
        try {
            return $decodor($raspuns->corp);
        } catch (FormaInvalida $e) {
            throw new EroareRaspunsInvalid(
                'Raspunsul API-ului nu are forma asteptata: ' . $e->getMessage(),
                status: $raspuns->status,
                raspuns: $raspuns->corp,
                previous: $e,
            );
        }
    }

    /**
     * @param array<string, string|null> $query
     * @param array<string, mixed>|null $corp
     */
    private function trimiteOData(string $metoda, string $cale, array $query, ?array $corp): Raspuns
    {
        $url = $this->s->baseUrl . $cale;
        $parametri = array_filter($query, static fn(?string $v): bool => $v !== null);
        if ($parametri !== []) {
            $url .= '?' . http_build_query($parametri, '', '&', PHP_QUERY_RFC3986);
        }

        $cerere = $this->s->cereri->createRequest($metoda, $url)
            ->withHeader('Authorization', 'Bearer ' . $this->s->apiKey)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->s->userAgent);
        if ($corp !== null) {
            try {
                $json = json_encode($corp, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } catch (\JsonException $e) {
                throw new EroareValidare('Datele trimise contin text care nu e UTF-8 valid.', cod: 'VALIDARE_LOCALA', previous: $e);
            }
            $cerere = $cerere
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->s->fluxuri->createStream($json));
        }

        try {
            $raspuns = $this->s->http->sendRequest($cerere);
        } catch (ClientExceptionInterface $e) {
            $timeout = self::esteTimeout($e);
            throw new EroareRetea(
                $timeout
                    ? "Cererea {$metoda} {$cale} a depasit timpul de raspuns: {$e->getMessage()}"
                    : "Cererea {$metoda} {$cale} nu a primit raspuns: {$e->getMessage()}",
                previous: $e,
                timeout: $timeout,
                neajunsa: !$timeout && self::esteNeajunsa($e),
            );
        }

        $status = $raspuns->getStatusCode();
        $headere = [];
        foreach (array_keys($raspuns->getHeaders()) as $nume) {
            $headere[strtolower((string) $nume)] = $raspuns->getHeaderLine((string) $nume);
        }

        try {
            $flux = $raspuns->getBody();
            if ($flux->isSeekable()) {
                $flux->rewind();
            }
            $text = $flux->getContents();
        } catch (\RuntimeException $e) {
            throw new EroareRetea("Raspunsul la {$metoda} {$cale} s-a intrerupt: {$e->getMessage()}", previous: $e);
        }

        $ok = $status >= 200 && $status < 300;
        $valoare = null;
        if ($text !== '') {
            try {
                $valoare = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                if ($ok) {
                    throw new EroareRaspunsInvalid('API-ul a raspuns cu un corp care nu e JSON.', status: $status, raspuns: $text, previous: $e);
                }
                $valoare = $text;
            }
        }

        if (!$ok) {
            throw Erori::dinRaspuns($status, $headere, $valoare, $this->s->ceas->acumMs(), $this->s->urlImplicit);
        }

        return new Raspuns($status, $headere, $valoare);
    }

    private static function esteTimeout(\Throwable $e): bool
    {
        if (self::errnoCurl($e) === self::ERRNO_TIMEOUT) {
            return true;
        }
        $mesaje = self::mesaje($e);

        return str_contains($mesaje, 'timed out') || str_contains($mesaje, 'timeout');
    }

    private static function esteNeajunsa(\Throwable $e): bool
    {
        if (in_array(self::errnoCurl($e), self::ERRNO_NEAJUNSA, true)) {
            return true;
        }
        $mesaje = self::mesaje($e);

        return str_contains($mesaje, 'could not resolve host') || str_contains($mesaje, 'connection refused');
    }

    /** Codul cURL, cand clientul il expune (Guzzle: `getHandlerContext()['errno']`). */
    private static function errnoCurl(\Throwable $e): ?int
    {
        for ($curent = $e, $i = 0; $curent !== null && $i < 4; $curent = $curent->getPrevious(), ++$i) {
            if (method_exists($curent, 'getHandlerContext')) {
                $context = $curent->getHandlerContext();
                if (is_array($context) && is_int($context['errno'] ?? null)) {
                    return $context['errno'];
                }
            }
        }

        return null;
    }

    private static function mesaje(\Throwable $e): string
    {
        $text = '';
        for ($curent = $e, $i = 0; $curent !== null && $i < 4; $curent = $curent->getPrevious(), ++$i) {
            $text .= ' ' . strtolower($curent->getMessage());
        }

        return $text;
    }
}
