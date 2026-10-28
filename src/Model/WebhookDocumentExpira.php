<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\TipVerificare;
use Acteo\Internal\Decodor;

/** Evenimentul `document.expira`: un document a intrat intr-un prag de expirare. */
final class WebhookDocumentExpira
{
    public const EVENIMENT = 'document.expira';

    /** Mereu `"document.expira"`. */
    public readonly string $event;

    /**
     * @param string $id ID unic al livrarii; foloseste-l pentru deduplicare
     * @param VehiculWebhook $vehicul vehiculul la care expira documentul
     * @param TipVerificare $document documentul care expira
     * @param \DateTimeImmutable $expiraLa data expirarii documentului
     * @param int $zileRamase zile intregi ramase pana la expirare (minim 0)
     * @param int $prag pragul de notificare atins, in zile (RCA/ITP/rovinieta: 30, 14, 3; ARR: 60, 50, 45)
     * @param \DateTimeImmutable $trimisLa cand a fost trimisa notificarea
     */
    public function __construct(
        public readonly string $id,
        public readonly VehiculWebhook $vehicul,
        public readonly TipVerificare $document,
        public readonly \DateTimeImmutable $expiraLa,
        public readonly int $zileRamase,
        public readonly int $prag,
        public readonly \DateTimeImmutable $trimisLa,
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
            vehicul: VehiculWebhook::fromArray(Decodor::obiect($d, 'vehicul', ''), 'vehicul'),
            document: Decodor::enum($d, 'document', '', TipVerificare::class),
            expiraLa: Decodor::data($d, 'expiraLa', ''),
            zileRamase: Decodor::intreg($d, 'zileRamase', ''),
            prag: Decodor::intreg($d, 'prag', ''),
            trimisLa: Decodor::data($d, 'trimisLa', ''),
        );
    }
}
