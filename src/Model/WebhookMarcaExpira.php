<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\PragMarca;
use Acteo\Internal\Decodor;

/** Evenimentul `marca.expira`: o marca urmarita intra intr-un prag de expirare. */
final class WebhookMarcaExpira implements WebhookNotificare
{
    public const EVENIMENT = 'marca.expira';

    /** Mereu `"marca.expira"`. */
    public readonly string $event;

    /**
     * @param string $id ID unic al livrarii; foloseste-l pentru deduplicare
     * @param \DateTimeImmutable $trimisLa cand a fost trimisa notificarea
     * @param MarcaWebhook $marca marca urmarita
     * @param string $expiraLa data expirarii, `yyyy-mm-dd`
     * @param PragMarca $prag pragul atins
     * @param string|null $reinnoirePanaLa la `Gratie`: ultima zi (`yyyy-mm-dd`) in care se mai poate reinnoi; altfel null
     */
    public function __construct(
        public readonly string $id,
        public readonly \DateTimeImmutable $trimisLa,
        public readonly MarcaWebhook $marca,
        public readonly string $expiraLa,
        public readonly PragMarca $prag,
        public readonly ?string $reinnoirePanaLa,
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
            marca: MarcaWebhook::fromArray(Decodor::obiect($d, 'marca', ''), 'marca'),
            expiraLa: Decodor::sir($d, 'expiraLa', ''),
            prag: Decodor::enum($d, 'prag', '', PragMarca::class),
            reinnoirePanaLa: Decodor::sirSauNull($d, 'reinnoirePanaLa', ''),
        );
    }
}
