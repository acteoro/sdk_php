<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Internal\Decodor;

/** Evenimentul `termen.expira`: un termen adaugat in cont intra in pragul de 30 sau de 3 zile. */
final class WebhookTermenExpira implements WebhookNotificare
{
    public const EVENIMENT = 'termen.expira';

    /** Mereu `"termen.expira"`. */
    public readonly string $event;

    /**
     * @param string $id ID unic al livrarii; foloseste-l pentru deduplicare
     * @param \DateTimeImmutable $trimisLa cand a fost trimisa notificarea
     * @param TermenWebhook $termen termenul din cont
     * @param int $zileRamase zile intregi ramase pana la scadenta
     * @param int $prag pragul atins, in zile: 30 sau 3
     */
    public function __construct(
        public readonly string $id,
        public readonly \DateTimeImmutable $trimisLa,
        public readonly TermenWebhook $termen,
        public readonly int $zileRamase,
        public readonly int $prag,
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
            termen: TermenWebhook::fromArray(Decodor::obiect($d, 'termen', ''), 'termen'),
            zileRamase: Decodor::intreg($d, 'zileRamase', ''),
            prag: Decodor::intregDin($d, 'prag', '', [30, 3]),
        );
    }
}
