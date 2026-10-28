<?php

declare(strict_types=1);

namespace Acteo\Model;

use Acteo\Enum\TipVerificare;
use Acteo\Exception\EroareValidare;
use Acteo\Internal\Decodor;
use Acteo\Internal\FormaInvalida;

/**
 * Verificare pusa in coada: sursa oficiala e interogata acum. Paseaz-o la
 * `rezultat()` sau `asteapta()` ca sa afli raspunsul.
 *
 * Poate fi salvata cu `json_encode()` (de exemplu intr-un job de coada) si
 * refacuta cu `VerificareInCurs::fromArray()`.
 */
final class VerificareInCurs implements \JsonSerializable
{
    public const STATUS = 'processing';

    private const FORMAT_DATA = 'Y-m-d\TH:i:s.vP';

    /** Mereu `"processing"`. */
    public readonly string $status;

    /**
     * @param TipVerificare $tip tipul verificarii
     * @param string $query identificatorul dupa care se face pollingul
     * @param string|null $serieSasiu seria de sasiu trimisa la polling; completata doar la rovinieta
     * @param int $since momentul (ms) de la care un rezultat e considerat nou
     * @param string|null $requestId prezent cand ai trimis `callbackUrl`; apare si in webhook
     * @param \DateTimeImmutable|null $queuedAt cand a intrat verificarea in coada
     * @param string|null $hint recomandarea API-ului, ex: sa trimiti si VIN-ul la RCA
     * @param Limite|null $limite cota ramasa dupa cererea POST (nu se serializeaza)
     */
    public function __construct(
        public readonly TipVerificare $tip,
        public readonly string $query,
        public readonly ?string $serieSasiu,
        public readonly int $since,
        public readonly ?string $requestId = null,
        public readonly ?\DateTimeImmutable $queuedAt = null,
        public readonly ?string $hint = null,
        public readonly ?Limite $limite = null,
    ) {
        $this->status = self::STATUS;
    }

    /**
     * @return array{status: string, tip: string, query: string, serieSasiu: string|null, since: int, requestId: string|null, queuedAt: string|null, hint: string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'tip' => $this->tip->value,
            'query' => $this->query,
            'serieSasiu' => $this->serieSasiu,
            'since' => $this->since,
            'requestId' => $this->requestId,
            'queuedAt' => $this->queuedAt?->format(self::FORMAT_DATA),
            'hint' => $this->hint,
        ];
    }

    /**
     * Reface o verificare in curs salvata cu `json_encode()`.
     *
     * @param array<mixed> $date rezultatul `json_decode($json, true)`
     *
     * @throws EroareValidare datele nu descriu o verificare in curs
     */
    public static function fromArray(array $date): self
    {
        try {
            return new self(
                tip: Decodor::enum($date, 'tip', '', TipVerificare::class),
                query: Decodor::sir($date, 'query', ''),
                serieSasiu: Decodor::sirOptional($date, 'serieSasiu', ''),
                since: Decodor::intreg($date, 'since', ''),
                requestId: Decodor::sirOptional($date, 'requestId', ''),
                queuedAt: Decodor::dataOptionala($date, 'queuedAt', ''),
                hint: Decodor::sirOptional($date, 'hint', ''),
            );
        } catch (FormaInvalida $e) {
            throw new EroareValidare(
                'Verificarea in curs nu poate fi refacuta: ' . $e->getMessage(),
                cod: 'VALIDARE_LOCALA',
                previous: $e,
                camp: 'referinta',
            );
        }
    }
}
