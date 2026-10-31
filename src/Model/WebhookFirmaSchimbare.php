<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Internal\Decodor;
use Acteo\Internal\FormaInvalida;

/** Evenimentul `firma.schimbare`: ANAF a schimbat starea unei firme monitorizate. */
final class WebhookFirmaSchimbare implements WebhookNotificare
{
    public const EVENIMENT = 'firma.schimbare';

    /** Mereu `"firma.schimbare"`. */
    public readonly string $event;

    /**
     * @param string $id ID unic al livrarii; foloseste-l pentru deduplicare
     * @param \DateTimeImmutable $trimisLa cand a fost trimisa notificarea
     * @param FirmaWebhook $firma firma monitorizata
     * @param list<SchimbareFirma> $schimbari campurile schimbate
     */
    public function __construct(
        public readonly string $id,
        public readonly \DateTimeImmutable $trimisLa,
        public readonly FirmaWebhook $firma,
        public readonly array $schimbari,
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
        $schimbari = [];
        foreach (Decodor::lista($d, 'schimbari', '') as $i => $s) {
            if (!is_array($s)) {
                throw new FormaInvalida("schimbari.{$i}: se astepta obiect");
            }
            $schimbari[] = SchimbareFirma::fromArray($s, "schimbari.{$i}");
        }

        return new self(
            id: Decodor::sir($d, 'id', ''),
            trimisLa: Decodor::data($d, 'trimisLa', ''),
            firma: FirmaWebhook::fromArray(Decodor::obiect($d, 'firma', ''), 'firma'),
            schimbari: $schimbari,
        );
    }
}
