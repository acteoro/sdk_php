<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Internal\Decodor;

/** Evenimentul `firma.dosar`: dosar nou in instanta pe numele unei firme monitorizate. */
final class WebhookFirmaDosar implements WebhookNotificare
{
    public const EVENIMENT = 'firma.dosar';

    /** Mereu `"firma.dosar"`. */
    public readonly string $event;

    /**
     * @param string $id ID unic al livrarii; foloseste-l pentru deduplicare
     * @param \DateTimeImmutable $trimisLa cand a fost trimisa notificarea
     * @param FirmaWebhook $firma firma monitorizata
     * @param DosarFirma $dosar dosarul nou
     */
    public function __construct(
        public readonly string $id,
        public readonly \DateTimeImmutable $trimisLa,
        public readonly FirmaWebhook $firma,
        public readonly DosarFirma $dosar,
    ) {
        $this->event = self::EVENIMENT;
    }

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(
            id: Decodor::sir($d, 'id', ''),
            trimisLa: Decodor::data($d, 'trimisLa', ''),
            firma: FirmaWebhook::fromArray(Decodor::obiect($d, 'firma', ''), 'firma'),
            dosar: DosarFirma::fromArray(Decodor::obiect($d, 'dosar', ''), 'dosar'),
        );
    }
}
