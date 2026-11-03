<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\StatusPolitaPad;
use Acteo\Internal\Decodor;

/**
 * Payloadul POST trimis la `callbackUrl` cand verificarea unei polite PAD se
 * termina: `Done` cu verdictul sau `Error` cu `code`. Corpul contine doar
 * CNP-ul mascat.
 */
final class WebhookRezultatPad
{
    /**
     * @param StatusPolitaPad $status `Done` sau `Error`
     * @param string $requestId ID-ul cererii, acelasi cu cel primit de la `porneste()`
     * @param string $cnpMascat CNP-ul cu cifrele din mijloc ascunse, ex: `1*********012`
     * @param string $judet judetul, cum apare in nomenclatorul PADROM
     * @param string $localitate localitatea, cum apare in nomenclatorul PADROM
     * @param string $laData data la care s-a verificat polita, `AAAA-LL-ZZ`
     * @param bool|null $valabila la `Done`: `true` daca PADROM a gasit o polita valabila la data ceruta
     * @param string|null $serieNumar seria si numarul politei, cand PADROM le arata
     * @param string|null $distribuitor asiguratorul care a emis polita, cand PADROM il arata
     * @param \DateTimeImmutable|null $verificatLa la `Done`: cand a raspuns PADROM
     * @param string|null $code la `Error`: motivul (`INVALID_IDENTIFIER`, `UPSTREAM_UNAVAILABLE`, `UPSTREAM_TIMEOUT`, `UPSTREAM_BLOCKED`, `UPSTREAM_CHANGED`)
     * @param string|null $error la `Error`: mesajul, in romana
     */
    public function __construct(
        public readonly StatusPolitaPad $status,
        public readonly string $requestId,
        public readonly string $cnpMascat,
        public readonly string $judet,
        public readonly string $localitate,
        public readonly string $laData,
        public readonly ?bool $valabila = null,
        public readonly ?string $serieNumar = null,
        public readonly ?string $distribuitor = null,
        public readonly ?\DateTimeImmutable $verificatLa = null,
        public readonly ?string $code = null,
        public readonly ?string $error = null,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d): self
    {
        $status = Decodor::enumDin($d, 'status', '', StatusPolitaPad::class, [StatusPolitaPad::Done, StatusPolitaPad::Error]);
        $baza = [
            'status' => $status,
            'requestId' => Decodor::sir($d, 'requestId', ''),
            'cnpMascat' => Decodor::sir($d, 'cnpMascat', ''),
            'judet' => Decodor::sir($d, 'judet', ''),
            'localitate' => Decodor::sir($d, 'localitate', ''),
            'laData' => Decodor::sir($d, 'laData', ''),
        ];
        if ($status === StatusPolitaPad::Error) {
            return new self(...$baza, code: Decodor::sirOptional($d, 'code', ''), error: Decodor::sir($d, 'error', ''));
        }

        return new self(
            ...$baza,
            valabila: Decodor::bool($d, 'valabila', ''),
            serieNumar: Decodor::sirSauNull($d, 'serieNumar', ''),
            distribuitor: Decodor::sirSauNull($d, 'distribuitor', ''),
            verificatLa: Decodor::data($d, 'verificatLa', ''),
        );
    }
}
