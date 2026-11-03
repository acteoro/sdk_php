<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\StatusPolitaPad;
use Acteo\Internal\Decodor;

/**
 * Raspunsul la `rezultat()` sau `asteapta()` pentru o polita PAD: verdictul
 * PADROM (`Done`) sau cererea inca in curs (`Processing`). O verificare
 * incheiata cu eroare arunca `EroareVerificare`.
 */
final class RezultatPolitaPad
{
    /**
     * @param StatusPolitaPad $status `Done` sau `Processing`
     * @param string $requestId ID-ul cererii
     * @param string $cnpMascat CNP-ul cu cifrele din mijloc ascunse, ex: `1*********012`
     * @param string $judet judetul, cum apare in nomenclatorul PADROM
     * @param string $localitate localitatea, cum apare in nomenclatorul PADROM
     * @param string $laData data la care s-a verificat polita, `AAAA-LL-ZZ`
     * @param bool|null $valabila la `Done`: `true` daca PADROM a gasit o polita valabila la data ceruta; `null` cat e in curs
     * @param string|null $serieNumar seria si numarul politei, cand PADROM le arata
     * @param string|null $distribuitor asiguratorul care a emis polita, cand PADROM il arata
     * @param \DateTimeImmutable|null $verificatLa la `Done`: cand a raspuns PADROM
     * @param \DateTimeImmutable|null $queuedAt la `Processing`: cand a pornit verificarea
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
        public readonly ?\DateTimeImmutable $queuedAt = null,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d corp cu `status` `processing` sau `done`
     */
    public static function fromArray(array $d): self
    {
        $status = Decodor::enumDin($d, 'status', '', StatusPolitaPad::class, [StatusPolitaPad::Processing, StatusPolitaPad::Done]);
        $baza = [
            'status' => $status,
            'requestId' => Decodor::sir($d, 'requestId', ''),
            'cnpMascat' => Decodor::sir($d, 'cnpMascat', ''),
            'judet' => Decodor::sir($d, 'judet', ''),
            'localitate' => Decodor::sir($d, 'localitate', ''),
            'laData' => Decodor::sir($d, 'laData', ''),
        ];
        if ($status === StatusPolitaPad::Processing) {
            return new self(...$baza, queuedAt: Decodor::data($d, 'queuedAt', ''));
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
