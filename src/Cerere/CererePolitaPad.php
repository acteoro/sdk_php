<?php

declare(strict_types=1);

namespace Acteo\Cerere;

use Acteo\Exception\EroareValidare;
use Acteo\Internal\Chei;

/**
 * Datele unei verificari de polita PAD. Locuinta se da prin `judet` si
 * `localitate` (cu sau fara diacritice), ori prin `judetId` si `localitateId`
 * din nomenclatorul PADROM.
 *
 * Exemplu: `new CererePolitaPad(cnp: $cnp, judet: 'Cluj', localitate: 'Cluj-Napoca', acord: true)`.
 */
final class CererePolitaPad
{
    /**
     * @param string $cnp CNP-ul proprietarului, 13 cifre; spatiile si liniutele sunt acceptate. API-ul nu il pastreaza.
     * @param string|null $judet judetul locuintei: numele (`Cluj`) sau indicativul (`CJ`, `B`); la Bucuresti trimite si `sector`
     * @param string|null $localitate localitatea locuintei, ex: `Cluj-Napoca`; la Bucuresti poate lipsi
     * @param int|null $sector doar la Bucuresti: sectorul, de la 1 la 6
     * @param string|null $judetId alternativa la `judet`: id-ul judetului din nomenclatorul PADROM; cere si `localitateId`
     * @param string|null $localitateId alternativa la `localitate`: id-ul localitatii din nomenclatorul PADROM
     * @param string|null $data data la care verifici polita, `AAAA-LL-ZZ`, cel mult 365 de zile inainte sau dupa azi; implicit azi
     * @param bool $acord `true`: ai acordul proprietarului sau esti chiar proprietarul; obligatoriu
     */
    public function __construct(
        public readonly string $cnp,
        public readonly ?string $judet = null,
        public readonly ?string $localitate = null,
        public readonly ?int $sector = null,
        public readonly ?string $judetId = null,
        public readonly ?string $localitateId = null,
        public readonly ?string $data = null,
        public readonly bool $acord = false,
    ) {}

    /**
     * @internal
     *
     * @param array<mixed>|self $cerere array cu cheile `cnp`, `judet`, `localitate`, `sector`, `judetId`, `localitateId`, `data`, `acord`
     *
     * @throws EroareValidare array cu chei necunoscute sau cu valori de alt tip
     */
    public static function din(array|self $cerere): self
    {
        if ($cerere instanceof self) {
            return $cerere;
        }
        Chei::verifica($cerere, ['cnp', 'judet', 'localitate', 'sector', 'judetId', 'localitateId', 'data', 'acord']);
        $cnp = $cerere['cnp'] ?? '';
        if (!is_string($cnp)) {
            throw self::eroare('Trimite `cnp` ca text.', 'cnp');
        }
        $siruri = [];
        foreach (['judet', 'localitate', 'judetId', 'localitateId', 'data'] as $camp) {
            $v = $cerere[$camp] ?? null;
            if ($v !== null && !is_string($v)) {
                throw self::eroare("Trimite `{$camp}` ca text.", $camp);
            }
            $siruri[$camp] = $v;
        }
        $sector = $cerere['sector'] ?? null;
        if ($sector !== null && !is_int($sector)) {
            throw self::eroare('Trimite `sector` ca numar de la 1 la 6.', 'sector');
        }
        $acord = $cerere['acord'] ?? false;

        return new self(
            cnp: $cnp,
            judet: $siruri['judet'],
            localitate: $siruri['localitate'],
            sector: $sector,
            judetId: $siruri['judetId'],
            localitateId: $siruri['localitateId'],
            data: $siruri['data'],
            acord: $acord === true,
        );
    }

    private static function eroare(string $mesaj, string $camp): EroareValidare
    {
        return new EroareValidare($mesaj, cod: 'VALIDARE_LOCALA', camp: $camp);
    }
}
