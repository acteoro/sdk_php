<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\StatusPolitaPad;
use Acteo\Internal\Decodor;

/**
 * Verificarea PAD a pornit: PADROM e interogat acum. Paseaz-o la `rezultat()`
 * sau `asteapta()`, ori pastreaza doar `requestId`.
 */
final class PolitaPadPornita
{
    /** Mereu `StatusPolitaPad::Processing`. */
    public readonly StatusPolitaPad $status;

    /**
     * @param string $requestId ID-ul cererii; il folosesti la `rezultat()` si apare si in webhook
     * @param string $cnpMascat CNP-ul cu cifrele din mijloc ascunse, ex: `1*********012`
     * @param string $judet judetul, cum apare in nomenclatorul PADROM
     * @param string $localitate localitatea, cum apare in nomenclatorul PADROM
     * @param string $laData data la care se verifica polita, `AAAA-LL-ZZ`
     * @param \DateTimeImmutable $queuedAt cand a pornit verificarea
     * @param string $pollUrl cererea GET de polling, cu id-ul deja completat
     * @param Limite|null $limite cota ramasa dupa cererea POST, cand API-ul o trimite
     */
    public function __construct(
        public readonly string $requestId,
        public readonly string $cnpMascat,
        public readonly string $judet,
        public readonly string $localitate,
        public readonly string $laData,
        public readonly \DateTimeImmutable $queuedAt,
        public readonly string $pollUrl,
        public readonly ?Limite $limite = null,
    ) {
        $this->status = StatusPolitaPad::Processing;
    }

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d, ?Limite $limite = null): self
    {
        Decodor::enumDin($d, 'status', '', StatusPolitaPad::class, [StatusPolitaPad::Processing]);

        return new self(
            requestId: Decodor::sir($d, 'requestId', ''),
            cnpMascat: Decodor::sir($d, 'cnpMascat', ''),
            judet: Decodor::sir($d, 'judet', ''),
            localitate: Decodor::sir($d, 'localitate', ''),
            laData: Decodor::sir($d, 'laData', ''),
            queuedAt: Decodor::data($d, 'queuedAt', ''),
            pollUrl: Decodor::sir($d, 'pollUrl', ''),
            limite: $limite,
        );
    }
}
