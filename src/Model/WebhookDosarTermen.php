<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Internal\Decodor;

/** Evenimentul `dosar.termen`: se apropie o sedinta intr-un dosar monitorizat. */
final class WebhookDosarTermen implements WebhookNotificare
{
    public const EVENIMENT = 'dosar.termen';

    /** Mereu `"dosar.termen"`. */
    public readonly string $event;

    /**
     * @param string $id ID unic al livrarii; foloseste-l pentru deduplicare
     * @param \DateTimeImmutable $trimisLa cand a fost trimisa notificarea
     * @param DosarWebhook $dosar dosarul monitorizat
     * @param SedintaDosar $sedinta sedinta care se apropie
     * @param int $zileRamase 3 sau 1: reminder cu 3 zile si cu o zi inainte de sedinta
     */
    public function __construct(
        public readonly string $id,
        public readonly \DateTimeImmutable $trimisLa,
        public readonly DosarWebhook $dosar,
        public readonly SedintaDosar $sedinta,
        public readonly int $zileRamase,
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
            dosar: DosarWebhook::fromArray(Decodor::obiect($d, 'dosar', ''), 'dosar'),
            sedinta: SedintaDosar::fromArray(Decodor::obiect($d, 'sedinta', ''), 'sedinta'),
            zileRamase: Decodor::intregDin($d, 'zileRamase', '', [1, 3]),
        );
    }
}
