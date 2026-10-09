<?php

declare(strict_types=1);

namespace VerificRca\Model;

use VerificRca\Internal\Decodor;

/** Un vehicul monitorizat din cont, cu datele de expirare ale documentelor. */
final class Vehicul
{
    /**
     * @param string $id ID-ul vehiculului in cont, folosit la stergere
     * @param string|null $numarInmatriculare null daca vehiculul a fost adaugat doar cu VIN
     * @param string|null $serieSasiu null daca vehiculul a fost adaugat doar cu numarul
     * @param \DateTimeImmutable|null $rcaExpiraLa null daca nu e cunoscuta inca
     * @param \DateTimeImmutable|null $itpExpiraLa null daca nu e cunoscuta inca (ITP-ul cere VIN)
     * @param \DateTimeImmutable|null $rovinietaExpiraLa null daca nu e cunoscuta inca (rovinieta cere numar, VIN si numarul talonului)
     * @param \DateTimeImmutable|null $arrExpiraLa completata doar cand `arrMonitorizat` e true
     * @param bool $arrMonitorizat vehiculul e monitorizat si pentru copia conforma ARR
     * @param bool $activ vehiculul e verificat automat si primeste notificari de expirare
     * @param \DateTimeImmutable $createdAt cand a fost adaugat vehiculul in cont
     * @param string|null $serieTalon numarul certificatului de inmatriculare (talon); fara el rovinieta nu se verifica
     * @param bool $serieTalonRespinsa sursa a respins numarul talonului; rovinieta nu se mai verifica pana il corectezi
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $numarInmatriculare,
        public readonly ?string $serieSasiu,
        public readonly ?\DateTimeImmutable $rcaExpiraLa,
        public readonly ?\DateTimeImmutable $itpExpiraLa,
        public readonly ?\DateTimeImmutable $rovinietaExpiraLa,
        public readonly ?\DateTimeImmutable $arrExpiraLa,
        public readonly bool $arrMonitorizat,
        public readonly bool $activ,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?string $serieTalon = null,
        public readonly bool $serieTalonRespinsa = false,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed> $d
     */
    public static function fromArray(array $d, string $prefix = ''): self
    {
        return new self(
            id: Decodor::sir($d, 'id', $prefix),
            numarInmatriculare: Decodor::sirSauNull($d, 'numarInmatriculare', $prefix),
            serieSasiu: Decodor::sirSauNull($d, 'serieSasiu', $prefix),
            rcaExpiraLa: Decodor::dataSauNull($d, 'rcaExpiraLa', $prefix),
            itpExpiraLa: Decodor::dataSauNull($d, 'itpExpiraLa', $prefix),
            rovinietaExpiraLa: Decodor::dataSauNull($d, 'rovinietaExpiraLa', $prefix),
            arrExpiraLa: Decodor::dataSauNull($d, 'arrExpiraLa', $prefix),
            arrMonitorizat: Decodor::bool($d, 'arrMonitorizat', $prefix),
            activ: Decodor::bool($d, 'activ', $prefix),
            createdAt: Decodor::data($d, 'createdAt', $prefix),
            serieTalon: Decodor::sirOptional($d, 'serieTalon', $prefix),
            serieTalonRespinsa: array_key_exists('serieTalonRespinsa', $d) && Decodor::bool($d, 'serieTalonRespinsa', $prefix),
        );
    }
}
